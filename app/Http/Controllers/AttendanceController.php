<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Teacher;
use App\Services\SessionRescheduler;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\MakeupRecoveryEngine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    private function campusId(Request $request): ?int
    {
        return \App\Support\CampusScope::campusIdFor($request->user());
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $sessionsQuery = ClassSession::with('group');
        if ($this->campusId($request)) {
            $sessionsQuery->where('campus_id', $this->campusId($request));
        }

        if ($user?->role === 'teacher') {
            $teacher = Teacher::where('user_id', $user->id)->orWhere('email', $user->email)->first();
            $teacherId = $teacher?->id ?? -1;
            $sessionsQuery->whereHas('group', fn ($q) => $q->where('teacher_id', $teacherId));
        }

        $sessionId = $request->integer('class_session_id');
        $session = $sessionId ? (clone $sessionsQuery)->where('id', $sessionId)->first() : null;
        $enrollments = $session ? Enrollment::with(['student.representatives'])->where('group_id', $session->group_id)->get() : collect();

        $records = $session
            ? AttendanceRecord::where('class_session_id', $session->id)->get()->keyBy('enrollment_id')
            : collect();

        $previousSession = null;
        $previousRecords = collect();
        if ($session) {
            $previousSession = ClassSession::query()
                ->where('group_id', $session->group_id)
                ->where(function (Builder $builder) use ($session) {
                    $builder
                        ->whereDate('session_date', '<', $session->session_date)
                        ->orWhere(function (Builder $nested) use ($session) {
                            $nested
                                ->whereDate('session_date', '=', $session->session_date)
                                ->where('id', '<', $session->id);
                        });
                })
                ->orderByDesc('session_date')
                ->orderByDesc('id')
                ->first();

            if ($previousSession) {
                $previousRecords = AttendanceRecord::query()
                    ->where('class_session_id', $previousSession->id)
                    ->get()
                    ->keyBy('enrollment_id');
            }
        }

        $sessionHoliday = $session?->session_date
            ? Holiday::query()->active()->forCampus((int) $session->campus_id)->get()
                ->first(fn (Holiday $holiday) => $holiday->occursOn($session->session_date))
            : null;

        return view('attendance.index', [
            'sessions' => $sessionsQuery->latest('session_date')->take(100)->get(),
            'selectedSession' => $session,
            'sessionHoliday' => $sessionHoliday,
            'canRecordAttendance' => $session?->canRecordAttendance() ?? false,
            'enrollments' => $enrollments,
            'records' => $records,
            'previousSession' => $previousSession,
            'previousRecords' => $previousRecords,
            'statuses' => [
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_JUSTIFIED,
            ],
        ]);
    }

    public function reschedule(Request $request, SessionRescheduler $rescheduler): RedirectResponse
    {
        $data = $request->validate([
            'class_session_id' => ['required', 'exists:class_sessions,id'],
            'session_date' => ['required', 'date'],
            'cascade' => ['nullable', 'boolean'],
        ]);

        $this->authorizeSession($request, (int) $data['class_session_id']);

        $session = ClassSession::query()->findOrFail($data['class_session_id']);
        $previousDate = $session->session_date?->format('d/m/Y');
        $newDate = Carbon::parse($data['session_date'])->startOfDay();
        $cascade = (bool) ($data['cascade'] ?? false);

        try {
            $shifted = $cascade
                ? $rescheduler->rescheduleWithCascade($session, $newDate)
                : tap(0, fn () => $rescheduler->reschedule($session, $newDate));
        } catch (ValidationException $exception) {
            return redirect()
                ->route('attendance.index', ['class_session_id' => $session->id])
                ->withErrors($exception->errors())
                ->withInput();
        }

        AuditTrail::log($request, $cascade ? 'session.reschedule.cascade' : 'session.reschedule', $session, [
            'from' => $previousDate,
            'to' => $newDate->toDateString(),
            'shifted_following' => $shifted,
        ]);

        $message = "Clase movida del {$previousDate} al {$newDate->format('d/m/Y')}.";
        if ($cascade) {
            $message .= $shifted > 0
                ? " Se corrieron {$shifted} clase(s) siguiente(s) con su asistencia."
                : ' No hizo falta correr otras clases.';
        }

        return redirect()
            ->route('attendance.index', ['class_session_id' => $session->id])
            ->with('success', $message);
    }

    private function authorizeSession(Request $request, int $sessionId): void
    {
        if ($request->user()?->role === 'teacher') {
            $teacher = Teacher::where('user_id', $request->user()->id)->orWhere('email', $request->user()->email)->first();
            $allowed = ClassSession::where('id', $sessionId)
                ->whereHas('group', fn ($q) => $q->where('teacher_id', $teacher?->id ?? -1))
                ->exists();

            if (! $allowed) {
                abort(403);
            }
        }

        if ($this->campusId($request)) {
            $sessionCampusMatches = ClassSession::where('id', $sessionId)
                ->where('campus_id', $this->campusId($request))
                ->exists();
            if (! $sessionCampusMatches) {
                abort(403);
            }
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_session_id' => ['required', 'exists:class_sessions,id'],
            'topic' => ['nullable', 'string'],
            'program_status' => ['nullable', 'in:on_track,delayed'],
            'program_notes' => ['nullable', 'string'],
            'records' => ['required', 'array'],
            'records.*.enrollment_id' => ['required', 'exists:enrollments,id'],
            'records.*.status' => ['required', 'in:present,absent,late,justified'],
            'records.*.notes' => ['nullable', 'string'],
        ]);

        $this->authorizeSession($request, (int) $data['class_session_id']);

        $session = ClassSession::query()->findOrFail($data['class_session_id']);
        if (! $session->canRecordAttendance()) {
            return redirect()
                ->route('attendance.index', ['class_session_id' => $session->id])
                ->withErrors(['class_session_id' => 'La asistencia solo puede registrarse el día de la sesión o después.']);
        }

        ClassSession::where('id', $data['class_session_id'])->update([
            'topic' => $data['topic'] ?? null,
            'program_status' => $data['program_status'] ?? null,
            'program_notes' => $data['program_notes'] ?? null,
        ]);

        $written = 0;
        foreach ($data['records'] as $entry) {
            $record = AttendanceRecord::updateOrCreate(
                [
                    'class_session_id' => $data['class_session_id'],
                    'enrollment_id' => $entry['enrollment_id'],
                ],
                [
                    'status' => $entry['status'],
                    'notes' => $entry['notes'] ?? null,
                ],
            );
            MakeupRecoveryEngine::syncForAttendanceRecord($record);
            $written++;
        }

        AuditTrail::log($request, 'attendance.upsert', null, [
            'class_session_id' => $data['class_session_id'],
            'records_written' => $written,
        ]);
        AlertEngine::evaluateAttendanceForSession((int) $data['class_session_id']);

        return redirect()
            ->route('attendance.index', ['class_session_id' => $data['class_session_id']])
            ->with('success', 'Asistencia guardada.');
    }
}
