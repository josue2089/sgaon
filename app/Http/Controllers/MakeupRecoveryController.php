<?php

namespace App\Http\Controllers;

use App\Models\MakeupBooking;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MakeupRecoveryController extends Controller
{
    private function campusId(Request $request): ?int
    {
        return \App\Support\CampusScope::campusIdFor($request->user());
    }

    public function index(Request $request): View
    {
        $campusId = $this->campusId($request);

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

        $studentsQuery = Student::query()->orderBy('first_name')->orderBy('last_name');
        $teachersQuery = Teacher::query()->orderBy('first_name')->orderBy('last_name');
        $programsQuery = Program::query()->where('status', 'active')->orderBy('name');
        $levelsQuery = ProgramLevel::query()->where('status', 'active')->orderBy('sort_order');
        $schedulesQuery = ScheduleTemplate::query()->where('status', 'active')->orderBy('starts_at');

        if ($campusId) {
            $requestsQuery->where('campus_id', $campusId);
            $sessionsQuery->where('campus_id', $campusId);
            $studentsQuery->where('campus_id', $campusId);
            $teachersQuery->where('campus_id', $campusId);
            $schedulesQuery->where('campus_id', $campusId);
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'student_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'program_level_id' => ['nullable', 'integer'],
            'session_date' => ['nullable', 'date'],
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
        if (! empty($filters['session_date'])) {
            $sessionsQuery->whereDate('session_date', $filters['session_date']);
        }

        $summaryQuery = MakeupRequest::query();
        if ($campusId) {
            $summaryQuery->where('campus_id', $campusId);
        }

        return view('makeups.index', [
            'requests' => $requestsQuery->paginate(20)->withQueryString(),
            'makeupSessions' => $sessionsQuery->get(),
            'students' => $studentsQuery->get(),
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
            'session_date' => ['required', 'date'],
            'starts_at' => ['required'],
            'ends_at' => ['required'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'status' => ['required', 'in:open,full,cancelled,completed'],
            'notes' => ['nullable', 'string'],
        ]);

        $teacher = Teacher::findOrFail((int) $data['teacher_id']);
        if ($this->campusId($request) && (int) $teacher->campus_id !== (int) $this->campusId($request)) {
            abort(403);
        }

        $data['campus_id'] = $teacher->campus_id;
        $makeupSession = MakeupSession::create($data);
        MakeupRecoveryEngine::syncSessionStatus($makeupSession->fresh()->loadCount('activeBookings'));

        return back()->with('success', 'Bloque recuperativo creado.');
    }

    public function reviewRequest(Request $request, MakeupRequest $makeupRequest): RedirectResponse
    {
        $this->authorizeCampus($request, $makeupRequest->campus_id);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
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

    private function authorizeCampus(Request $request, ?int $campusId): void
    {
        if ($this->campusId($request) && (int) $this->campusId($request) !== (int) $campusId) {
            abort(403);
        }
    }
}
