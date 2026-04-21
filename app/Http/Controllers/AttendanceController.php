<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $sessionsQuery = ClassSession::with('group');
        if ($user?->campus_id) {
            $sessionsQuery->where('campus_id', $user->campus_id);
        }

        if ($user?->role === 'teacher') {
            $teacher = Teacher::where('user_id', $user->id)->orWhere('email', $user->email)->first();
            $teacherId = $teacher?->id ?? -1;
            $sessionsQuery->whereHas('group', fn ($q) => $q->where('teacher_id', $teacherId));
        }

        $sessionId = $request->integer('class_session_id');
        $session = $sessionId ? (clone $sessionsQuery)->where('id', $sessionId)->first() : null;
        $enrollments = $session ? Enrollment::with('student')->where('group_id', $session->group_id)->get() : collect();

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

        return view('attendance.index', [
            'sessions' => $sessionsQuery->latest('session_date')->take(100)->get(),
            'selectedSession' => $session,
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

        if ($request->user()?->role === 'teacher') {
            $teacher = Teacher::where('user_id', $request->user()->id)->orWhere('email', $request->user()->email)->first();
            $allowed = ClassSession::where('id', $data['class_session_id'])
                ->whereHas('group', fn ($q) => $q->where('teacher_id', $teacher?->id ?? -1))
                ->exists();

            if (! $allowed) {
                abort(403);
            }
        }

        if ($request->user()?->campus_id) {
            $sessionCampusMatches = ClassSession::where('id', $data['class_session_id'])
                ->where('campus_id', $request->user()->campus_id)
                ->exists();
            if (! $sessionCampusMatches) {
                abort(403);
            }
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
