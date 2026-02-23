<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Student;

class AlertEngine
{
    public static function evaluateAttendanceForSession(int $classSessionId): void
    {
        $session = ClassSession::with('group')->find($classSessionId);
        if (! $session) {
            return;
        }

        $enrollments = Enrollment::where('group_id', $session->group_id)->withCount([
            'attendanceRecords as absent_last_30' => fn ($q) => $q
                ->where('status', 'absent')
                ->whereHas('classSession', fn ($qq) => $qq->whereDate('session_date', '>=', now()->subDays(30))),
        ])->get();

        foreach ($enrollments as $enrollment) {
            if ($enrollment->absent_last_30 >= 3) {
                self::openOrUpdateAlert(
                    campusId: $session->campus_id,
                    studentId: $enrollment->student_id,
                    type: 'attendance',
                    message: "Inasistencia acumulada ({$enrollment->absent_last_30}) en 30 días",
                );
            }
        }
    }

    public static function evaluateFinanceForStudent(int $studentId): void
    {
        $student = Student::find($studentId);
        if (! $student) {
            return;
        }

        $overdueCount = Charge::where('student_id', $studentId)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        if ($overdueCount > 0) {
            self::openOrUpdateAlert(
                campusId: $student->campus_id,
                studentId: $student->id,
                type: 'finance',
                message: "Mora activa en {$overdueCount} cargo(s)",
            );

            Charge::where('student_id', $studentId)
                ->whereIn('status', ['pending', 'partial'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->update(['status' => 'overdue']);

            return;
        }

        self::resolveOpenAlerts($student->id, 'finance');
    }

    private static function openOrUpdateAlert(int $campusId, int $studentId, string $type, string $message): void
    {
        Alert::updateOrCreate(
            [
                'campus_id' => $campusId,
                'student_id' => $studentId,
                'type' => $type,
                'status' => 'open',
            ],
            [
                'message' => $message,
                'resolved_at' => null,
            ],
        );
    }

    private static function resolveOpenAlerts(int $studentId, string $type): void
    {
        Alert::where('student_id', $studentId)
            ->where('type', $type)
            ->where('status', 'open')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }
}
