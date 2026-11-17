<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesCampusAccess;
use App\Models\MakeupBooking;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\AuditTrail;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MakeupRecoveryController extends Controller
{
    use ScopesCampusAccess;

    public function index(Request $request): View
    {
        $requestsQuery = MakeupRequest::query()
            ->with([
                'student',
                'enrollment.group.course.program',
                'enrollment.group.course.programLevel',
                'missedSession',
                'attachments',
                'booking.makeupSession.teacher',
                'charge',
                'payment.receipt',
                'validator',
            ])
            ->latest();

        $sessionsQuery = MakeupSession::query()
            ->with(['teacher', 'program', 'programLevel', 'scheduleTemplate'])
            ->withCount('activeBookings')
            ->orderBy('session_date')
            ->orderBy('starts_at');

        $studentsQuery = Student::query();
        $teachersQuery = Teacher::query()->orderBy('first_name')->orderBy('last_name');
        $programsQuery = Program::query()->where('status', 'active')->orderBy('name');
        $levelsQuery = ProgramLevel::query()->where('status', 'active')->orderBy('sort_order');
        $schedulesQuery = ScheduleTemplate::query()->where('status', 'active')->orderBy('starts_at');

        foreach ([$requestsQuery, $sessionsQuery, $studentsQuery, $teachersQuery, $schedulesQuery] as $scopedQuery) {
            $this->applyCampusScope($scopedQuery);
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'student_id' => ['nullable', 'integer'],
            'sessions' => ['nullable', 'in:upcoming,past'],
            'program_id' => ['nullable', 'integer'],
            'program_level_id' => ['nullable', 'integer'],
            'session_date' => ['nullable', 'date', 'sane_date'],
        ]);

        if (! empty($filters['status'])) {
            $requestsQuery->where('status', $filters['status']);
        }
        if (! empty($filters['student_id'])) {
            $requestsQuery->where('student_id', (int) $filters['student_id']);
        }
        if (! empty($filters['program_id'])) {
            $requestsQuery->whereHas('enrollment.group.course', fn ($query) => $query->where('program_id', (int) $filters['program_id']));
            $sessionsQuery->where('program_id', (int) $filters['program_id']);
        }
        if (! empty($filters['program_level_id'])) {
            $requestsQuery->whereHas('enrollment.group.course', fn ($query) => $query->where('program_level_id', (int) $filters['program_level_id']));
            $sessionsQuery->where('program_level_id', (int) $filters['program_level_id']);
        }
        // #452: por defecto solo los bloques de hoy en adelante; "Anteriores" muestra los últimos 60 días.
        $sessionsScope = $request->query('sessions') === 'past' ? 'past' : 'upcoming';
        if (! empty($filters['session_date'])) {
            $sessionsQuery->whereDate('session_date', $filters['session_date']);
        } elseif ($sessionsScope === 'past') {
            $sessionsQuery->whereDate('session_date', '<', now()->toDateString())
                ->whereDate('session_date', '>=', now()->subDays(60)->toDateString())
                ->reorder()->orderByDesc('session_date')->orderByDesc('starts_at');
        } else {
            $sessionsQuery->whereDate('session_date', '>=', now()->toDateString());
        }

        $summaryQuery = $this->applyCampusScope(MakeupRequest::query());

        return view('makeups.index', [
            'requests' => $requestsQuery->paginate(20)->withQueryString(),
            'makeupSessions' => $sessionsQuery->limit(200)->get(),
            'sessionsScope' => $sessionsScope,
            // #452: el filtro de alumno busca en students.search; solo se carga el ya elegido.
            'students' => $studentsQuery->whereKey((int) ($filters['student_id'] ?? 0))->get(),
            'teachers' => $teachersQuery->get(),
            'programs' => $programsQuery->get(),
            'programLevels' => $levelsQuery->get(),
            'scheduleTemplates' => $schedulesQuery->get(),
            'filters' => $filters,
            'summary' => [
                'pending_validation' => (clone $summaryQuery)->where('status', MakeupRequest::STATUS_PENDING_VALIDATION)->count(),
                'approved_for_booking' => (clone $summaryQuery)->where('status', MakeupRequest::STATUS_APPROVED_FOR_BOOKING)->count(),
                'booked' => (clone $summaryQuery)->where('status', MakeupRequest::STATUS_BOOKED)->count(),
                'completed' => (clone $summaryQuery)->where('status', MakeupRequest::STATUS_COMPLETED)->count(),
                'missed' => (clone $summaryQuery)->where('status', MakeupRequest::STATUS_MISSED)->count(),
            ],
        ]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'exists:teachers,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'program_level_id' => ['required', 'exists:program_levels,id'],
            'schedule_template_id' => ['nullable', 'exists:schedule_templates,id'],
            'session_date' => ['required', 'date', 'sane_date'],
            'starts_at' => ['required'],
            'ends_at' => ['required'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'status' => ['required', 'in:open,full,cancelled,completed'],
            'notes' => ['nullable', 'string'],
        ]);

        $teacher = Teacher::findOrFail((int) $data['teacher_id']);
        if (! $this->canAccessCampus((int) $teacher->campus_id)) {
            abort(403);
        }
        $this->ensureLevelBelongsToProgram($data);

        $data['campus_id'] = $teacher->campus_id;
        $makeupSession = MakeupSession::create($data);
        MakeupRecoveryEngine::syncSessionStatus($makeupSession->fresh()->loadCount('activeBookings'));

        return back()->with('success', 'Bloque recuperativo creado.');
    }

    /**
     * #452: editar un bloque. El cupo no puede quedar por debajo de las reservas; si cambia la fecha o la hora,
     * se avisa por correo a quienes lo tenían reservado.
     */
    public function updateSession(Request $request, MakeupSession $makeupSession): RedirectResponse
    {
        $this->authorizeCampus($request, $makeupSession->campus_id);
        if (in_array($makeupSession->status, ['cancelled', 'completed'], true)) {
            return back()->withErrors(['session' => 'Este bloque ya está cancelado o completado; no se puede editar.']);
        }

        $data = $request->validate([
            'teacher_id' => ['required', 'exists:teachers,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'program_level_id' => ['required', 'exists:program_levels,id'],
            'session_date' => ['required', 'date', 'sane_date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->ensureLevelBelongsToProgram($data);

        $teacher = Teacher::findOrFail((int) $data['teacher_id']);
        if ((int) $teacher->campus_id !== (int) $makeupSession->campus_id) {
            return back()->withErrors(['teacher_id' => 'El profesor debe ser de la misma sede del bloque.'])->withInput();
        }

        $booked = $makeupSession->activeBookings()->count();
        if ((int) $data['capacity'] < $booked) {
            return back()->withErrors(['capacity' => "El cupo no puede ser menor que las {$booked} reserva(s) que ya tiene."])->withInput();
        }

        $before = [
            'date' => $makeupSession->session_date?->toDateString(),
            'starts_at' => substr((string) $makeupSession->starts_at, 0, 5),
            'ends_at' => substr((string) $makeupSession->ends_at, 0, 5),
        ];
        $makeupSession->update($data);
        MakeupRecoveryEngine::syncSessionStatus($makeupSession->fresh()->loadCount('activeBookings'));

        $moved = $before !== ['date' => $data['session_date'], 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at']];
        $notified = $moved ? MakeupRecoveryEngine::notifySessionChange($makeupSession, 'rescheduled', $before) : 0;
        AuditTrail::log($request, 'makeups.session.update', $makeupSession, ['before' => $before, 'notified' => $notified]);

        return back()->with('success', $notified > 0
            ? "Bloque actualizado. Se avisó del cambio a {$notified} alumno(s) con reserva."
            : 'Bloque actualizado.');
    }

    /** #452: cancelar un bloque. Las reservas se liberan (la recuperativa vuelve a quedar por reservar) y se avisa a cada alumno. */
    public function cancelSession(Request $request, MakeupSession $makeupSession): RedirectResponse
    {
        $this->authorizeCampus($request, $makeupSession->campus_id);
        if ($makeupSession->status === 'cancelled') {
            return back()->with('info', 'El bloque ya estaba cancelado.');
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $released = MakeupRecoveryEngine::cancelSession($makeupSession, $data['reason'] ?? null);
        AuditTrail::log($request, 'makeups.session.cancel', $makeupSession, ['released' => $released, 'reason' => $data['reason'] ?? null]);

        return back()->with('success', $released > 0
            ? "Bloque cancelado. {$released} alumno(s) con reserva quedaron avisados y pueden reservar otro horario."
            : 'Bloque cancelado.');
    }

    public function reviewRequest(Request $request, MakeupRequest $makeupRequest): RedirectResponse
    {
        $this->authorizeCampus($request, $makeupRequest->campus_id);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date', 'sane_date'],
            'method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['action'] === 'approve') {
            if (! $makeupRequest->loadMissing('attachments')->payment_proof) {
                return back()->withErrors([
                    'action' => 'Debes tener un comprobante de pago cargado para aprobar la solicitud.',
                ]);
            }

            $makeupRequest->forceFill([
                'validated_by' => $request->user()?->id,
            ])->save();
            MakeupRecoveryEngine::approve($makeupRequest, [
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'method' => $data['method'] ?? 'Comprobante validado',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $request->user()?->id,
            ]);

            return back()->with('success', 'Pago validado y recuperativa habilitada para reserva.');
        }

        $makeupRequest->forceFill([
            'validated_by' => $request->user()?->id,
        ])->save();
        MakeupRecoveryEngine::reject($makeupRequest, $data['rejection_reason'] ?? 'Comprobante rechazado.');

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function updateBooking(Request $request, MakeupBooking $booking): RedirectResponse
    {
        $booking->loadMissing('makeupRequest');
        $this->authorizeCampus($request, $booking->makeupRequest?->campus_id);

        $data = $request->validate([
            'status' => ['required', 'in:reserved,attended,missed,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        MakeupRecoveryEngine::updateBookingStatus($booking, $data['status'], $data['notes'] ?? null);

        return back()->with('success', 'Estado de recuperativa actualizado.');
    }

    private function ensureLevelBelongsToProgram(array $data): void
    {
        $levelProgram = ProgramLevel::query()->whereKey((int) $data['program_level_id'])->value('program_id');
        if ((int) $levelProgram !== (int) $data['program_id']) {
            throw ValidationException::withMessages(['program_level_id' => 'El nivel no pertenece al programa elegido.']);
        }
    }

    private function authorizeCampus(Request $request, ?int $campusId): void
    {
        if (! $this->canAccessCampus($campusId)) {
            abort(403);
        }
    }
}
