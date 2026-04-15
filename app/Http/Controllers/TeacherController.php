<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\AuditLog;
use App\Models\ScheduleTemplate;
use App\Models\Teacher;
use App\Support\AuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TeacherController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(Request $request): View
    {
        $query = Teacher::query()->with('campus')->latest();
        $baseStatsQuery = Teacher::query();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
            $baseStatsQuery->where('campus_id', $this->campusId());
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"])
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $specialty = trim((string) $request->query('specialty', ''));
        if ($specialty !== '') {
            $query->whereHas('groups.course', fn (Builder $builder) => $builder->where('name', $specialty));
        }

        $teachers = $query->paginate(20)->withQueryString();
        $teacherIds = $teachers->getCollection()->pluck('id')->values();

        $studentsByTeacher = collect();
        $coursesByTeacher = collect();
        if ($teacherIds->isNotEmpty()) {
            $studentsByTeacher = DB::table('groups')
                ->join('enrollments', 'enrollments.group_id', '=', 'groups.id')
                ->whereIn('groups.teacher_id', $teacherIds)
                ->selectRaw('groups.teacher_id, COUNT(DISTINCT enrollments.student_id) as students_count')
                ->groupBy('groups.teacher_id')
                ->pluck('students_count', 'groups.teacher_id');

            $coursesByTeacher = Course::query()
                ->whereIn('teacher_id', $teacherIds)
                ->selectRaw('teacher_id, COUNT(*) as courses_count')
                ->groupBy('teacher_id')
                ->pluck('courses_count', 'teacher_id');
        }

        $studentsTotal = DB::table('groups')
            ->join('enrollments', 'enrollments.group_id', '=', 'groups.id')
            ->when($this->campusId(), fn ($builder) => $builder->where('groups.campus_id', $this->campusId()))
            ->selectRaw('COUNT(DISTINCT enrollments.student_id) as students_count')
            ->value('students_count');

        $specialties = DB::table('groups')
            ->join('courses', 'courses.id', '=', 'groups.course_id')
            ->when($this->campusId(), fn ($builder) => $builder->where('groups.campus_id', $this->campusId()))
            ->distinct()
            ->orderBy('courses.name')
            ->pluck('courses.name');

        return view('teachers.index', [
            'teachers' => $teachers,
            'studentsByTeacher' => $studentsByTeacher,
            'coursesByTeacher' => $coursesByTeacher,
            'specialties' => $specialties,
            'summary' => [
                'total' => (clone $baseStatsQuery)->count(),
                'active' => (clone $baseStatsQuery)->where('status', 'active')->count(),
                'students_total' => (int) $studentsTotal,
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'specialty' => $specialty,
            ],
        ]);
    }

    public function calendar(Request $request): View
    {
        $mode = in_array((string) $request->query('mode', 'day'), ['day', 'week'], true)
            ? (string) $request->query('mode', 'day')
            : 'day';
        $selectedDate = Carbon::parse((string) $request->query('date', now()->toDateString()))->startOfDay();
        $weekStart = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $teachers = Teacher::query()
            ->with('campus')
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $scheduleTemplates = ScheduleTemplate::query()
            ->where('status', 'active')
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->orderBy('starts_at')
            ->orderBy('ends_at')
            ->get();

        $teacherIds = $teachers->pluck('id');
        $sessions = ClassSession::query()
            ->with(['group.course.programLevel', 'group.teacher'])
            ->whereHas('group', function (Builder $builder) use ($teacherIds): void {
                $builder->whereIn('teacher_id', $teacherIds);
                if ($this->campusId()) {
                    $builder->where('campus_id', $this->campusId());
                }
            })
            ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get();

        $weekDays = collect(range(0, 5))->map(function (int $offset) use ($weekStart) {
            $date = $weekStart->copy()->addDays($offset);

            return [
                'key' => strtolower($date->format('D')),
                'short_label' => $date->translatedFormat('D'),
                'label' => $date->translatedFormat('l d/m'),
                'date' => $date,
                'is_today' => $date->isToday(),
            ];
        });

        $selectedDayKey = strtolower($selectedDate->format('D'));
        $selectedDay = [
            'key' => $selectedDayKey,
            'short_label' => $selectedDate->translatedFormat('D'),
            'label' => $selectedDate->translatedFormat('l d/m/Y'),
            'date' => $selectedDate,
            'is_today' => $selectedDate->isToday(),
        ];

        $buildRows = function (Carbon $date, string $dayKey) use ($scheduleTemplates, $teachers, $sessions) {
            $filteredSchedules = $scheduleTemplates
                ->filter(fn (ScheduleTemplate $schedule) => collect($schedule->days ?? [])->contains($dayKey))
                ->unique(fn (ScheduleTemplate $schedule) => $schedule->starts_at.'-'.$schedule->ends_at)
                ->values();

            return $filteredSchedules->map(function (ScheduleTemplate $schedule) use ($teachers, $sessions, $date) {
                $cells = $teachers->map(function (Teacher $teacher) use ($schedule, $sessions, $date) {
                    $matches = $sessions->filter(function (ClassSession $session) use ($teacher, $schedule, $date) {
                        return (int) ($session->group?->teacher_id ?? 0) === (int) $teacher->id
                            && $session->session_date?->isSameDay($date)
                            && $session->starts_at === $schedule->starts_at
                            && $session->ends_at === $schedule->ends_at;
                    })->values();

                    return [
                        'teacher' => $teacher,
                        'sessions' => $matches,
                        'occupied' => $matches->isNotEmpty(),
                        'conflict' => $matches->count() > 1,
                        'available' => $matches->isEmpty(),
                    ];
                });

                return [
                    'schedule' => $schedule,
                    'time_label' => Carbon::createFromFormat('H:i:s', $schedule->starts_at)->format('g:i').' - '.Carbon::createFromFormat('H:i:s', $schedule->ends_at)->format('g:i A'),
                    'cells' => $cells,
                ];
            })->values();
        };

        $dayRows = $buildRows($selectedDate, $selectedDayKey);
        $calendarDays = $weekDays->map(function (array $day) use ($buildRows) {
            return [
                'label' => $day['label'],
                'short_label' => $day['short_label'],
                'date' => $day['date'],
                'is_today' => $day['is_today'],
                'rows' => $buildRows($day['date'], $day['key']),
            ];
        });

        $occupiedCount = $dayRows->sum(fn ($row) => $row['cells']->where('occupied', true)->count());
        $availableCount = $dayRows->sum(fn ($row) => $row['cells']->where('available', true)->count());
        $conflictCount = $dayRows->sum(fn ($row) => $row['cells']->where('conflict', true)->count());

        return view('teachers.calendar', [
            'teachers' => $teachers,
            'mode' => $mode,
            'selectedDate' => $selectedDate,
            'selectedDay' => $selectedDay,
            'dayRows' => $dayRows,
            'calendarDays' => $calendarDays,
            'weekDays' => $weekDays,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'summary' => [
                'slots' => $dayRows->count(),
                'occupied' => $occupiedCount,
                'available' => $availableCount,
                'conflicts' => $conflictCount,
            ],
        ]);
    }

    public function show(Request $request, Teacher $teacher): View
    {
        $this->authorizeTeacher($teacher);

        $teacher->load(['campus']);
        $weekStart = Carbon::parse((string) $request->query('week_start', now()->toDateString()))->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $courseStatus = (string) $request->query('course_status', '');
        $studentStatus = (string) $request->query('student_status', '');
        $auditAction = (string) $request->query('audit_action', '');

        $courses = Course::query()
            ->with([
                'level',
                'program',
                'programLevel',
                'courseLevel',
                'period',
                'scheduleTemplate',
                'managedGroup.enrollments.student',
                'managedGroup.sessions' => fn ($query) => $query->withCount('attendanceRecords')->orderBy('sequence')->orderBy('session_date'),
            ])
            ->where('teacher_id', $teacher->id)
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $filteredCourses = $courses
            ->when($courseStatus !== '', fn ($collection) => $collection->where('status', $courseStatus))
            ->values();

        $studentRows = $filteredCourses
            ->flatMap(fn (Course $course) => ($course->managedGroup?->enrollments ?? collect())->map(function ($enrollment) use ($course) {
                return [
                    'student' => $enrollment->student,
                    'course' => $course,
                    'enrollment' => $enrollment,
                ];
            }))
            ->filter(fn (array $row) => $row['student'] !== null)
            ->when($studentStatus !== '', fn ($collection) => $collection->where('enrollment.status', $studentStatus))
            ->unique(fn (array $row) => $row['student']->id.'-'.$row['course']->id)
            ->values();

        $distinctStudentCount = $studentRows
            ->pluck('student.id')
            ->unique()
            ->count();

        $plannedSessions = $courses->sum(fn (Course $course) => $course->managedGroup?->sessions?->count() ?? 0);
        $completedSessions = $courses->sum(
            fn (Course $course) => ($course->managedGroup?->sessions ?? collect())->where('attendance_records_count', '>', 0)->count()
        );

        $attendanceStats = ClassSession::query()
            ->join('groups', 'groups.id', '=', 'class_sessions.group_id')
            ->join('attendance_records', 'attendance_records.class_session_id', '=', 'class_sessions.id')
            ->where('groups.teacher_id', $teacher->id)
            ->when($this->campusId(), fn ($builder) => $builder->where('groups.campus_id', $this->campusId()))
            ->selectRaw(
                "ROUND((SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100) as attendance_rate"
            )
            ->value('attendance_rate');

        $financeTotal = Charge::query()
            ->whereHas('course', fn (Builder $builder) => $builder->where('teacher_id', $teacher->id))
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->sum('amount');

        $upcomingSessions = ClassSession::query()
            ->with(['group.course.programLevel', 'group.course.courseLevel'])
            ->whereHas('group', function (Builder $builder) use ($teacher): void {
                $builder->where('teacher_id', $teacher->id);
                if ($this->campusId()) {
                    $builder->where('campus_id', $this->campusId());
                }
            })
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        $scheduleTemplates = ScheduleTemplate::query()
            ->where('status', 'active')
            ->when($teacher->campus_id, fn (Builder $builder) => $builder->where('campus_id', $teacher->campus_id))
            ->orderBy('starts_at')
            ->orderBy('ends_at')
            ->get();

        $calendarSessions = ClassSession::query()
            ->with(['group.course.programLevel'])
            ->whereHas('group', function (Builder $builder) use ($teacher): void {
                $builder->where('teacher_id', $teacher->id);
                if ($this->campusId()) {
                    $builder->where('campus_id', $this->campusId());
                }
            })
            ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get();

        $weekDays = collect(range(0, 5))
            ->map(function (int $offset) use ($weekStart) {
                $date = $weekStart->copy()->addDays($offset);

                return [
                    'key' => strtolower($date->format('D')),
                    'label' => $date->translatedFormat('D d/m'),
                    'date' => $date,
                ];
            });

        $calendarRows = $scheduleTemplates->map(function (ScheduleTemplate $schedule) use ($weekDays, $calendarSessions) {
            $cells = $weekDays->map(function (array $day) use ($schedule, $calendarSessions) {
                $isApplicable = collect($schedule->days ?? [])->contains($day['key']);
                $session = $calendarSessions->first(function (ClassSession $session) use ($schedule, $day) {
                    return $session->session_date?->isSameDay($day['date'])
                        && $session->starts_at === $schedule->starts_at
                        && $session->ends_at === $schedule->ends_at;
                });

                return [
                    'date' => $day['date'],
                    'label' => $day['label'],
                    'applicable' => $isApplicable,
                    'session' => $session,
                    'occupied' => $isApplicable && $session !== null,
                    'available' => $isApplicable && $session === null,
                ];
            });

            return [
                'schedule' => $schedule,
                'cells' => $cells,
            ];
        });

        $auditLogs = AuditLog::query()
            ->with('user')
            ->where('auditable_type', Teacher::class)
            ->where('auditable_id', $teacher->id)
            ->when($auditAction !== '', fn (Builder $builder) => $builder->where('event', 'like', 'teacher.'.$auditAction.'%'))
            ->latest()
            ->limit(12)
            ->get();

        return view('teachers.show', [
            'teacher' => $teacher,
            'courses' => $filteredCourses,
            'allCoursesCount' => $courses->count(),
            'studentRows' => $studentRows,
            'upcomingSessions' => $upcomingSessions,
            'calendarRows' => $calendarRows,
            'weekDays' => $weekDays,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'summary' => [
                'courses_total' => $courses->count(),
                'active_courses' => $courses->where('status', 'active')->count(),
                'students_total' => $distinctStudentCount,
                'planned_sessions' => $plannedSessions,
                'completed_sessions' => $completedSessions,
                'attendance_rate' => is_null($attendanceStats) ? null : (int) $attendanceStats,
                'finance_total' => (float) $financeTotal,
            ],
            'auditLogs' => $auditLogs,
            'filters' => [
                'course_status' => $courseStatus,
                'student_status' => $studentStatus,
                'audit_action' => $auditAction,
            ],
        ]);
    }

    public function create(): View
    {
        $campuses = Campus::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
        }

        return view('teachers.create', ['campuses' => $campuses->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_id' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string'],
            'profile_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo_path'] = $request->file('profile_photo')->store('profiles/teachers', 'public');
        }

        $teacher = Teacher::create($data);
        AuditTrail::log($request, 'teacher.create', $teacher, $data);

        return redirect()->route('teachers.index')->with('success', 'Profesor creado.');
    }

    public function edit(Teacher $teacher): View
    {
        $this->authorizeTeacher($teacher);

        $campuses = Campus::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
        }

        return view('teachers.edit', [
            'teacher' => $teacher,
            'campuses' => $campuses->get(),
            'auditLogs' => AuditLog::query()
                ->with('user')
                ->where('auditable_type', Teacher::class)
                ->where('auditable_id', $teacher->id)
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $this->authorizeTeacher($teacher);

        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_id' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string'],
            'profile_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        if ($request->hasFile('profile_photo')) {
            if ($teacher->profile_photo_path) {
                Storage::disk('public')->delete($teacher->profile_photo_path);
            }
            $data['profile_photo_path'] = $request->file('profile_photo')->store('profiles/teachers', 'public');
        }

        $teacher->update($data);
        AuditTrail::log($request, 'teacher.update', $teacher, $data);

        return redirect()->route('teachers.index')->with('success', 'Profesor actualizado.');
    }

    public function destroy(Request $request, Teacher $teacher): RedirectResponse
    {
        $this->authorizeTeacher($teacher);

        if ($teacher->profile_photo_path) {
            Storage::disk('public')->delete($teacher->profile_photo_path);
        }
        AuditTrail::log($request, 'teacher.delete', $teacher, [
            'first_name' => $teacher->first_name,
            'last_name' => $teacher->last_name,
            'email' => $teacher->email,
        ]);
        $teacher->delete();

        return redirect()->route('teachers.index')->with('success', 'Profesor eliminado.');
    }

    private function authorizeTeacher(Teacher $teacher): void
    {
        if ($this->campusId() && (int) $teacher->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
    }
}
