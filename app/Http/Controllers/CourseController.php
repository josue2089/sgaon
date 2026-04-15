<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Enrollment;
use App\Models\Period;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\CoursePlanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourseController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    private function resolveAcademicLevelIdFromProgram(?Program $program, int $campusId): int
    {
        $programName = trim((string) ($program?->name ?? ''));

        $level = AcademicLevel::query()
            ->where('campus_id', $campusId)
            ->where(function (Builder $builder) use ($programName) {
                $builder->where('name', $programName)
                    ->orWhere('code', $programName);
            })
            ->first();

        if (! $level) {
            $level = AcademicLevel::query()->create([
                'campus_id' => $campusId,
                'name' => $programName !== '' ? $programName : 'Programa',
                'code' => strtoupper(str_replace([' ', '-'], ['', ''], $programName !== '' ? $programName : 'PROGRAM')),
                'sort_order' => 999,
                'status' => 'active',
            ]);
        }

        return (int) $level->id;
    }

    public function index(Request $request): View
    {
        $query = Course::query()
            ->with(['campus', 'level', 'program', 'programLevel', 'courseLevel', 'teacher', 'period', 'scheduleTemplate', 'managedGroup'])
            ->latest();

        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        $statusFilter = (string) $request->query('status', '');
        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $levelFilter = (string) $request->query('level', '');
        if ($levelFilter !== '') {
            $query->whereHas('level', function (Builder $builder) use ($levelFilter) {
                $builder->where('code', $levelFilter)
                    ->orWhere('name', $levelFilter);
            });
        }

        $courses = $query->paginate(20)->withQueryString();

        $managedGroupIds = $courses->getCollection()->pluck('managed_group_id')->filter()->values();
        $studentsByGroup = $managedGroupIds->isNotEmpty()
            ? Enrollment::query()
                ->whereIn('group_id', $managedGroupIds)
                ->selectRaw('group_id, COUNT(*) as total_students')
                ->groupBy('group_id')
                ->pluck('total_students', 'group_id')
            : collect();

        $completedSessionsByGroup = $managedGroupIds->isNotEmpty()
            ? \App\Models\ClassSession::query()
                ->whereIn('group_id', $managedGroupIds)
                ->whereHas('attendanceRecords')
                ->selectRaw('group_id, COUNT(*) as completed_sessions')
                ->groupBy('group_id')
                ->pluck('completed_sessions', 'group_id')
            : collect();

        $plannedSessionsByGroup = $managedGroupIds->isNotEmpty()
            ? \App\Models\ClassSession::query()
                ->whereIn('group_id', $managedGroupIds)
                ->selectRaw('group_id, COUNT(*) as planned_sessions')
                ->groupBy('group_id')
                ->pluck('planned_sessions', 'group_id')
            : collect();

        $coursesCount = Course::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->count();
        $totalStudents = (int) $studentsByGroup->sum();
        $plannedSessions = Course::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->sum('academic_hours');

        $levelsQuery = AcademicLevel::query()
            ->select('academic_levels.id', 'academic_levels.name', 'academic_levels.code')
            ->selectRaw('COUNT(courses.id) as courses_count')
            ->leftJoin('courses', 'courses.academic_level_id', '=', 'academic_levels.id')
            ->groupBy('academic_levels.id', 'academic_levels.name', 'academic_levels.code')
            ->orderBy('academic_levels.sort_order');
        if ($this->campusId()) {
            $levelsQuery->where('academic_levels.campus_id', $this->campusId());
        }

        return view('courses.index', [
            'courses' => $courses,
            'studentsByGroup' => $studentsByGroup,
            'completedSessionsByGroup' => $completedSessionsByGroup,
            'plannedSessionsByGroup' => $plannedSessionsByGroup,
            'stats' => [
                'courses' => $coursesCount,
                'students' => $totalStudents,
                'planned_hours' => $plannedSessions ?: null,
            ],
            'levelStats' => $levelsQuery->get(),
            'courseLevels' => CourseLevel::query()
                ->where('status', 'active')
                ->orderBy('scale_position')
                ->get(),
            'programs' => Program::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => [
                'q' => $q,
                'status' => $statusFilter,
                'level' => $levelFilter,
            ],
            'levels' => AcademicLevel::query()
                ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['code', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('courses.create', $this->formData(new Course()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $studentIds = collect($request->input('student_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $course = Course::create($data);
        $course->load(['teacher', 'period', 'scheduleTemplate', 'managedGroup']);
        $course = CoursePlanner::sync($course);
        $this->addStudentsToCourse($course, $studentIds);

        return redirect()->route('courses.show', $course)->with('success', 'Curso creado y planificado.');
    }

    public function show(Course $course): View
    {
        $this->authorizeCourse($course);

        $course->load([
            'campus',
            'level',
            'program',
            'programLevel.lessons',
            'courseLevel',
            'teacher',
            'period',
            'scheduleTemplate',
            'managedGroup.teacher',
            'managedGroup.enrollments.student',
            'managedGroup.sessions' => fn ($query) => $query
                ->with('plannedLesson')
                ->withCount('attendanceRecords')
                ->orderBy('sequence')
                ->orderBy('session_date'),
        ]);

        $group = $course->managedGroup;
        $sessions = $group?->sessions ?? collect();
        $completedSessions = $sessions->where('attendance_records_count', '>', 0)->count();
        $pendingSessions = max(0, $sessions->count() - $completedSessions);
        $enrolledStudentIds = $group?->enrollments?->pluck('student_id')->all() ?? [];

        $availableStudents = Student::query()
            ->when($course->campus_id, fn (Builder $builder) => $builder->where('campus_id', $course->campus_id))
            ->when(! $course->campus_id && $this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->where('status', 'active')
            ->when(! empty($enrolledStudentIds), fn (Builder $builder) => $builder->whereNotIn('id', $enrolledStudentIds))
            ->with([
                'enrollments' => fn ($builder) => $builder
                    ->with(['group.course.courseLevel'])
                    ->with(['group.course.programLevel'])
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                    ->orderByDesc('enrolled_at')
                    ->orderByDesc('id'),
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('courses.show', [
            'course' => $course,
            'group' => $group,
            'sessions' => $sessions,
            'completedSessions' => $completedSessions,
            'pendingSessions' => $pendingSessions,
            'availableStudents' => $availableStudents,
        ]);
    }

    public function edit(Course $course): View
    {
        $this->authorizeCourse($course);

        return view('courses.edit', $this->formData($course->load('managedGroup.enrollments')));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $data = $this->validatedData($request);
        $studentIds = collect($request->input('student_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $course->update($data);
        $course->load(['teacher', 'period', 'scheduleTemplate', 'managedGroup.sessions.attendanceRecords']);
        $course = CoursePlanner::sync($course);
        $this->addStudentsToCourse($course, $studentIds);

        return redirect()->route('courses.show', $course)->with('success', 'Curso actualizado.');
    }

    public function syncStudents(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'exists:students,id'],
        ]);

        $course->load(['managedGroup']);
        if (! $course->managedGroup) {
            return back()->withErrors(['student_ids' => 'Configura primero el profesor, horario, período, fecha inicial y duración del curso.']);
        }

        $this->addStudentsToCourse($course, collect($data['student_ids'])->map(fn ($id) => (int) $id));

        return redirect()->route('courses.show', $course)->with('success', 'Estudiantes agregados al curso.');
    }

    public function removeStudent(Course $course, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeCourse($course);

        $course->load('managedGroup');
        if (! $course->managedGroup || (int) $enrollment->group_id !== (int) $course->managedGroup->id) {
            abort(404);
        }

        $enrollment->loadCount(['attendanceRecords', 'charges']);

        if ($enrollment->attendance_records_count > 0 || $enrollment->charges_count > 0) {
            $notes = trim((string) $enrollment->notes);
            $auditNote = 'Retirado desde detalle de curso el '.now()->format('d/m/Y H:i');

            $enrollment->update([
                'status' => 'withdrawn',
                'notes' => $notes === '' ? $auditNote : $notes."\n".$auditNote,
            ]);

            return redirect()
                ->route('courses.show', $course)
                ->with('success', 'El alumno fue marcado como retirado para preservar su historial.');
        }

        $enrollment->delete();

        return redirect()
            ->route('courses.show', $course)
            ->with('success', 'Alumno retirado del curso.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);
        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Curso eliminado.');
    }

    private function formData(Course $course): array
    {
        $campuses = Campus::orderBy('name');
        $teachers = Teacher::orderBy('first_name')->orderBy('last_name');
        $periods = Period::query()->where('status', 'active')->orderBy('code');
        $schedules = ScheduleTemplate::query()->where('status', 'active')->latest();
        $students = Student::query()->orderBy('first_name')->orderBy('last_name');
        $programs = Program::query()->where('status', 'active')->orderBy('name');
        $programLevels = ProgramLevel::query()->where('status', 'active')->with('program')->orderBy('program_id')->orderBy('sort_order');

        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
            $teachers->where('campus_id', $this->campusId());
            $periods->where('campus_id', $this->campusId());
            $schedules->where('campus_id', $this->campusId());
            $students->where('campus_id', $this->campusId());
        }

        return [
            'course' => $course,
            'campuses' => $campuses->get(),
            'programs' => $programs->get(),
            'programLevels' => $programLevels->get(),
            'teachers' => $teachers->get(),
            'periods' => $periods->get(),
            'schedules' => $schedules->get(),
            'students' => $students->get(),
            'selectedStudentIds' => $course->managedGroup?->enrollments?->pluck('student_id')->all() ?? [],
        ];
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'program_level_id' => ['required', 'exists:program_levels,id'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'period_id' => ['required', 'exists:periods,id'],
            'schedule_template_id' => ['required', 'exists:schedule_templates,id'],
            'name' => ['nullable', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'academic_hours' => ['required', 'integer', 'min:1', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $programLevel = ProgramLevel::query()->find($data['program_level_id']);
        if (! $programLevel || (int) $programLevel->program_id !== (int) $data['program_id']) {
            throw ValidationException::withMessages([
                'program_level_id' => 'El nivel seleccionado no pertenece al programa indicado.',
            ]);
        }

        $program = Program::query()->find($data['program_id']);
        $data['academic_level_id'] = $this->resolveAcademicLevelIdFromProgram($program, (int) $data['campus_id']);
        $data['course_level_id'] = null;

        $teacher = Teacher::query()->find($data['teacher_id']);
        $schedule = ScheduleTemplate::query()->find($data['schedule_template_id']);

        $nameCourse = new Course([
            'name' => $data['name'] ?? '',
        ]);
        $nameCourse->setRelation('programLevel', $programLevel);
        $nameCourse->setRelation('teacher', $teacher);
        $nameCourse->setRelation('scheduleTemplate', $schedule);
        $data['name'] = $nameCourse->buildStructuredName();

        return $data;
    }

    private function addStudentsToCourse(Course $course, Collection $studentIds): void
    {
        if ($studentIds->isEmpty() || ! $course->managedGroup) {
            return;
        }

        foreach ($studentIds as $studentId) {
            $student = Student::query()
                ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
                ->findOrFail($studentId);

            Enrollment::updateOrCreate(
                [
                    'group_id' => $course->managedGroup->id,
                    'student_id' => $student->id,
                ],
                [
                    'campus_id' => $course->campus_id,
                    'enrolled_at' => now()->toDateString(),
                    'status' => 'active',
                    'progress' => 0,
                ],
            );
        }
    }

    private function authorizeCourse(Course $course): void
    {
        if ($this->campusId() && (int) $course->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
    }
}
