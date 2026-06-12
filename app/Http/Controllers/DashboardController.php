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
use App\Support\CampusScope;
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

        $teacher = null;
        if ($user?->role === 'teacher') {
            $teacher = CampusScope::apply(
                Teacher::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('email', $user->email)),
                $user
            )->first();
        }

        $studentsQuery = CampusScope::apply(Student::query(), $user);
        $teachersQuery = CampusScope::apply(Teacher::query(), $user);
        $coursesQuery = CampusScope::apply(Course::query(), $user);
        $groupsQuery = CampusScope::apply(Group::query(), $user);
        $chargesQuery = CampusScope::apply(Charge::where('status', '!=', 'paid'), $user);
        $alertsQuery = CampusScope::apply(Alert::where('status', 'open'), $user);
        $attendanceQuery = AttendanceRecord::query();
        $paymentsQuery = CampusScope::apply(Payment::query(), $user);
        $monthChargesQuery = CampusScope::apply(
            Charge::query()->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]),
            $user
        );
        $selectedDate = request()->query('date');
        try {
            $selectedDate = $selectedDate ? Carbon::parse((string) $selectedDate)->startOfDay() : now()->startOfDay();
        } catch (\Throwable $e) {
            $selectedDate = now()->startOfDay();
        }

        $sessionsTodayQuery = CampusScope::apply(
            ClassSession::with(['group.course', 'group.teacher'])
                ->whereDate('session_date', $selectedDate->toDateString())
                ->orderBy('starts_at'),
            $user
        );

        $attendanceQuery->whereHas('classSession', fn ($q) => CampusScope::apply($q, $user));

        if ($teacher) {
            $studentsQuery->whereIn('id', Enrollment::query()
                ->select('student_id')
                ->where('status', 'active')
                ->whereHas('group', fn ($q) => $q->where('teacher_id', $teacher->id)));
            $coursesQuery->where('teacher_id', $teacher->id);
            $groupsQuery->where('teacher_id', $teacher->id);
            $teachersQuery->where('id', $teacher->id);
            $sessionsTodayQuery->whereHas('group', fn ($q) => $q->where('teacher_id', $teacher->id));
            $attendanceQuery->whereHas('classSession.group', fn ($q) => $q->where('teacher_id', $teacher->id));
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

        $weekStart = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $selectedDate->copy()->endOfWeek(Carbon::SUNDAY);
        $weekSessionsQuery = ClassSession::query()
            ->selectRaw('session_date, COUNT(*) as total')
            ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->groupBy('session_date');
        CampusScope::apply($weekSessionsQuery, $user);
        if ($teacher) {
            $weekSessionsQuery->whereHas('group', fn ($q) => $q->where('teacher_id', $teacher->id));
        }
        $weekCounts = (clone $weekSessionsQuery)->pluck('total', 'session_date');
        $weekDays = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $selectedDate, $weekCounts) {
            $day = $weekStart->copy()->addDays($offset);
            $dateKey = $day->toDateString();

            return [
                'date' => $day,
                'count' => (int) ($weekCounts[$dateKey] ?? 0),
                'selected' => $dateKey === $selectedDate->toDateString(),
            ];
        });

        $bestGroupAttendanceQuery = (clone $attendanceQuery)
            ->select('class_sessions.group_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as present_count"))
            ->join('class_sessions', 'class_sessions.id', '=', 'attendance_records.class_session_id')
            ->whereNotNull('class_sessions.group_id')
            ->groupBy('class_sessions.group_id')
            ->orderByDesc(DB::raw('CASE WHEN COUNT(*) > 0 THEN SUM(CASE WHEN attendance_records.status = \'present\' THEN 1 ELSE 0 END) / COUNT(*) ELSE 0 END'));

        if ($teacher) {
            $bestGroupAttendanceQuery
                ->join('groups', 'groups.id', '=', 'class_sessions.group_id')
                ->where('groups.teacher_id', $teacher->id);
        }

        $bestGroupAttendance = $bestGroupAttendanceQuery->first();

        $bestGroup = null;
        $bestGroupRate = null;
        if ($bestGroupAttendance) {
            $bestGroup = Group::find($bestGroupAttendance->group_id);
            $bestGroupRate = (int) round((((int) $bestGroupAttendance->present_count) / max(1, (int) $bestGroupAttendance->total)) * 100);
        }

        $attendanceByLevelQuery = AttendanceRecord::query()
            ->join('class_sessions', 'class_sessions.id', '=', 'attendance_records.class_session_id')
            ->join('groups', 'groups.id', '=', 'class_sessions.group_id')
            ->leftJoin('courses', 'courses.id', '=', 'groups.course_id')
            ->leftJoin('academic_levels', 'academic_levels.id', '=', 'courses.academic_level_id')
            ->selectRaw("COALESCE(NULLIF(academic_levels.code, ''), NULLIF(academic_levels.name, ''), 'N/D') as label")
            ->selectRaw("ROUND((SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100) as rate")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('rate');
        CampusScope::apply($attendanceByLevelQuery, $user, 'class_sessions.campus_id');
        if ($teacher) {
            $attendanceByLevelQuery->where('groups.teacher_id', $teacher->id);
        }

        $attendanceByTeacherQuery = AttendanceRecord::query()
            ->join('class_sessions', 'class_sessions.id', '=', 'attendance_records.class_session_id')
            ->join('groups', 'groups.id', '=', 'class_sessions.group_id')
            ->leftJoin('teachers', 'teachers.id', '=', 'groups.teacher_id')
            ->selectRaw("COALESCE(NULLIF(CONCAT(teachers.first_name, ' ', teachers.last_name), ' '), 'Sin asignar') as label")
            ->selectRaw("ROUND((SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100) as rate")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('rate');
        CampusScope::apply($attendanceByTeacherQuery, $user, 'class_sessions.campus_id');
        if ($teacher) {
            $attendanceByTeacherQuery->where('groups.teacher_id', $teacher->id);
        }

        $attendanceByGroupQuery = AttendanceRecord::query()
            ->join('class_sessions', 'class_sessions.id', '=', 'attendance_records.class_session_id')
            ->join('groups', 'groups.id', '=', 'class_sessions.group_id')
            ->selectRaw("COALESCE(NULLIF(groups.name, ''), 'Sin grupo') as label")
            ->selectRaw("ROUND((SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100) as rate")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('rate');
        CampusScope::apply($attendanceByGroupQuery, $user, 'class_sessions.campus_id');
        if ($teacher) {
            $attendanceByGroupQuery->where('groups.teacher_id', $teacher->id);
        }

        $teacherGradeCourses = collect();
        if ($teacher) {
            $teacherGradeCourses = CampusScope::apply(
                Course::query()->where('teacher_id', $teacher->id)->orderBy('name'),
                $user
            )->get(['id', 'name', 'code']);
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
            'weekDays' => $weekDays,
            'bestGroup' => $bestGroup,
            'bestGroupRate' => $bestGroupRate,
            'attendanceByLevel' => $attendanceByLevelQuery->take(5)->get(),
            'attendanceByTeacher' => $attendanceByTeacherQuery->take(5)->get(),
            'attendanceByGroup' => $attendanceByGroupQuery->take(5)->get(),
            'teacherGradeCourses' => $teacherGradeCourses,
        ]);
    }
}
