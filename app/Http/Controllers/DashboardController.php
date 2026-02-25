<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AttendanceRecord;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $user = request()->user();
        if ($user?->role === 'student') {
            return redirect()->route('portal.student');
        }
        if ($user?->role === 'representative') {
            return redirect()->route('portal.representative');
        }

        $campusId = $user?->campus_id;
        $studentsQuery = Student::query();
        $teachersQuery = Teacher::query();
        $coursesQuery = Course::query();
        $groupsQuery = Group::query();
        $chargesQuery = Charge::where('status', '!=', 'paid');
        $alertsQuery = Alert::where('status', 'open');
        $attendanceQuery = AttendanceRecord::query();
        $paymentsQuery = Payment::query();
        $monthChargesQuery = Charge::query()->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        $selectedDate = request()->query('date');
        try {
            $selectedDate = $selectedDate ? Carbon::parse((string) $selectedDate)->startOfDay() : now()->startOfDay();
        } catch (\Throwable $e) {
            $selectedDate = now()->startOfDay();
        }

        $sessionsTodayQuery = ClassSession::with(['group.course', 'group.teacher'])
            ->whereDate('session_date', $selectedDate->toDateString())
            ->orderBy('starts_at');

        if ($campusId) {
            $studentsQuery->where('campus_id', $campusId);
            $teachersQuery->where('campus_id', $campusId);
            $coursesQuery->where('campus_id', $campusId);
            $groupsQuery->where('campus_id', $campusId);
            $chargesQuery->where('campus_id', $campusId);
            $alertsQuery->where('campus_id', $campusId);
            $attendanceQuery->whereHas('classSession', fn ($q) => $q->where('campus_id', $campusId));
            $paymentsQuery->where('campus_id', $campusId);
            $monthChargesQuery->where('campus_id', $campusId);
            $sessionsTodayQuery->where('campus_id', $campusId);
        }

        $attendanceStats = (clone $attendanceQuery)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as present_count', [AttendanceRecord::STATUS_PRESENT])
            ->first();
        $attendanceTotal = (int) ($attendanceStats->total ?? 0);
        $attendanceRate = $attendanceTotal > 0 ? (int) round((((int) $attendanceStats->present_count) / $attendanceTotal) * 100) : null;

        $paymentsMonthAmount = (float) (clone $paymentsQuery)
            ->whereBetween('paid_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        $monthChargesTotal = (int) (clone $monthChargesQuery)->count();
        $monthChargesPaid = (int) (clone $monthChargesQuery)->where('status', 'paid')->count();
        $paymentsRate = $monthChargesTotal > 0 ? (int) round(($monthChargesPaid / $monthChargesTotal) * 100) : null;

        $todaySessions = (clone $sessionsTodayQuery)->take(6)->get();
        $groupIds = $todaySessions->pluck('group_id')->filter()->unique()->values();
        $enrollmentsByGroup = $groupIds->isEmpty()
            ? collect()
            : Enrollment::query()
                ->whereIn('group_id', $groupIds)
                ->select('group_id', DB::raw('COUNT(*) as total'))
                ->groupBy('group_id')
                ->pluck('total', 'group_id');
        $todaySessions = $todaySessions->map(function ($session) use ($enrollmentsByGroup) {
            $session->students_count = (int) ($enrollmentsByGroup[$session->group_id] ?? 0);
            return $session;
        });

        $bestGroupAttendance = (clone $attendanceQuery)
            ->select('class_sessions.group_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as present_count"))
            ->join('class_sessions', 'class_sessions.id', '=', 'attendance_records.class_session_id')
            ->whereNotNull('class_sessions.group_id')
            ->groupBy('class_sessions.group_id')
            ->orderByDesc(DB::raw('CASE WHEN COUNT(*) > 0 THEN SUM(CASE WHEN attendance_records.status = \'present\' THEN 1 ELSE 0 END) / COUNT(*) ELSE 0 END'))
            ->first();

        $bestGroup = null;
        $bestGroupRate = null;
        if ($bestGroupAttendance) {
            $bestGroup = Group::find($bestGroupAttendance->group_id);
            $bestGroupRate = (int) round((((int) $bestGroupAttendance->present_count) / max(1, (int) $bestGroupAttendance->total)) * 100);
        }

        return view('dashboard', [
            'studentsCount' => $studentsQuery->count(),
            'teachersCount' => $teachersQuery->count(),
            'coursesCount' => $coursesQuery->count(),
            'groupsCount' => $groupsQuery->count(),
            'pendingCharges' => $chargesQuery->sum('amount'),
            'openAlerts' => $alertsQuery->latest()->take(8)->get(),
            'openAlertsCount' => $alertsQuery->count(),
            'attendanceRate' => $attendanceRate,
            'paymentsRate' => $paymentsRate,
            'paymentsMonthAmount' => $paymentsMonthAmount,
            'todaySessions' => $todaySessions,
            'selectedDate' => $selectedDate,
            'previousDate' => $selectedDate->copy()->subDay(),
            'nextDate' => $selectedDate->copy()->addDay(),
            'bestGroup' => $bestGroup,
            'bestGroupRate' => $bestGroupRate,
        ]);
    }
}
