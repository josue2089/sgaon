<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesCampusAccess;
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
use App\Support\AuditTrail;
use App\Support\CoursePlanner;
use App\Services\EnrollmentBillingService;
use App\Services\HolidayCalendarSync;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CourseController extends Controller
{
    use ScopesCampusAccess;

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

        $context = $this->courseDetailContext($course);
        $enrolledStudentIds = $context['group']?->enrollments?->pluck('student_id')->all() ?? [];

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
                'representatives',
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('courses.show', $context + [
            'availableStudents' => $availableStudents,
            'holidaySessionIds' => CoursePlanner::sessionsOnHolidays($course)->pluck('id')->all(),
        ]);
    }

    public function generateExtracurricularCharges(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);
        abort_unless($course->isExtracurricular(), 404);

        $billing = app(EnrollmentBillingService::class);
        $created = 0;
        $students = 0;
        $course->loadMissing('managedGroup');
        Enrollment::query()
            ->where('status', 'active')
            ->whereHas('group', fn ($query) => $query->where('course_id', $course->id))
            ->get()
            ->each(function (Enrollment $enrollment) use ($billing, $request, &$created, &$students): void {
                $count = $billing->createAllMonthlyCharges($enrollment, $request)->count();
                $created += $count;
                $students += $count > 0 ? 1 : 0;
            });

        AuditTrail::log($request, 'course.extracurricular_charges.generate', $course, ['created' => $created, 'students' => $students]);

        return redirect()->route('courses.show', $course)->with('success', $created > 0
            ? "Se generaron {$created} cuota(s) faltante(s) para {$students} alumno(s)."
            : 'Todas las inscripciones activas ya tienen sus cuotas generadas.');
    }

    public function storeExtraSession(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $data = $request->validate([
            'session_date' => ['required', 'date', 'sane_date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
        ]);

        $session = CoursePlanner::addExtraSession(
            $course->loadMissing(['scheduleTemplate', 'managedGroup']),
            \Illuminate\Support\Carbon::parse($data['session_date']),
            $data['starts_at'] ?? null,
            $data['ends_at'] ?? null,
        );
        AuditTrail::log($request, 'course.session.extra', $course, $session->toArray());

        return redirect()->route('courses.show', $course)->with('success', 'Clase extra agregada el '.$session->session_date->format('d/m/Y').'.');
    }

    public function recalculateCalendar(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $result = app(HolidayCalendarSync::class)->resyncCourse($course);
        AuditTrail::log($request, 'course.calendar.recalculate', $course, $result);

        $messages = HolidayCalendarSync::flashMessages($result, 'Calendario recalculado.');
        if ($result['updated'] === 0) {
            unset($messages['success']);
        }

        return redirect()->route('courses.show', $course)->with($messages);
    }

    public function reportPdf(Course $course): Response
    {
        $this->authorizeCourse($course);

        $context = $this->courseDetailContext($course);
        $filename = 'curso-'.($course->code ?: $course->id).'.pdf';

        $pdf = Pdf::loadView('courses.course-report-pdf', $context + [
            'logoDataUri' => $this->buildLogoDataUri(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
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

        $calendarSnapshot = $this->calendarSnapshot($course);
        $previousLevelId = (int) $course->program_level_id;

        $course->update($data);
        AuditTrail::log($request, 'course.update', $course, $course->getChanges());
        $course->load(['teacher', 'period', 'scheduleTemplate', 'managedGroup.sessions.attendanceRecords']);
        $course = CoursePlanner::sync($course, $this->calendarFieldsChanged($calendarSnapshot, $course));
        $this->addStudentsToCourse($course, $studentIds);

        $redirect = redirect()->route('courses.show', $course)->with('success', 'Curso actualizado.');
        if ($previousLevelId && $previousLevelId !== (int) $course->program_level_id && $course->enrollments()->exists()) {
            $redirect->with('warning', 'Cambiaste el nivel de un curso que ya tiene alumnos. Las mensualidades del nivel anterior siguen en sus fichas: revísalas o anúlalas si ya se pagaron. Para pasar a los alumnos al siguiente nivel conviene crear un curso nuevo.');
        }

        return $redirect;
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

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        if (! $request->user()?->isMasterAdmin()) {
            abort(403);
        }

        $this->authorizeCourse($course);

        AuditTrail::log($request, 'course.delete', $course, [
            'name' => $course->name,
            'code' => $course->code,
        ]);

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
            'start_date' => ['required', 'date', 'sane_date'],
            'academic_hours' => ['nullable', 'integer', 'min:1', 'max:500'],
            'end_date' => ['nullable', 'date', 'sane_date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $programLevel = ProgramLevel::query()->find($data['program_level_id']);
        if (! $programLevel || (int) $programLevel->program_id !== (int) $data['program_id']) {
            throw ValidationException::withMessages([
                'program_level_id' => 'El nivel seleccionado no pertenece al programa indicado.',
            ]);
        }

        $program = Program::query()->find($data['program_id']);

        // Extracurricular: el curso dura hasta su fecha de fin; los regulares, según sus horas académicas.
        if ($program?->is_extracurricular) {
            if (empty($data['end_date'])) {
                throw ValidationException::withMessages([
                    'end_date' => 'Indica la fecha de fin del curso extracurricular (fin del año escolar).',
                ]);
            }
            $data['academic_hours'] = null;
        } else {
            if (empty($data['academic_hours'])) {
                throw ValidationException::withMessages([
                    'academic_hours' => 'Indica la duración del curso en horas académicas.',
                ]);
            }
            unset($data['end_date']);
        }

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

            $enrollment = Enrollment::updateOrCreate(
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

            app(EnrollmentBillingService::class)->createTuitionCharge($enrollment, request());
        }
    }

    private function authorizeCourse(Course $course): void
    {
        $this->authorizeCampus($course->campus_id);
    }

    private function courseDetailContext(Course $course): array
    {
        $course->load([
            'campus',
            'level',
            'program',
            'programLevel.program',
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

        return [
            'course' => $course,
            'group' => $group,
            'sessions' => $sessions,
            'completedSessions' => $completedSessions,
            'pendingSessions' => $pendingSessions,
        ];
    }

    private function buildLogoDataUri(): ?string
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    private function calendarSnapshot(Course $course): array
    {
        $course->loadMissing('scheduleTemplate', 'managedGroup.sessions');

        return [
            'start_date' => $course->start_date?->toDateString(),
            'schedule_template_id' => (int) $course->schedule_template_id,
            'academic_hours' => (int) $course->academic_hours,
            'end_date' => $course->end_date?->toDateString(),
            'period_id' => (int) $course->period_id,
            'schedule_starts_at' => CoursePlanner::normalizeTime($course->scheduleTemplate?->starts_at),
            'schedule_ends_at' => CoursePlanner::normalizeTime($course->scheduleTemplate?->ends_at),
            'schedule_days' => json_encode($course->scheduleTemplate?->days ?? []),
        ];
    }

    private function calendarFieldsChanged(array $before, Course $course): bool
    {
        $course->loadMissing('scheduleTemplate');

        return $before['start_date'] !== $course->start_date?->toDateString()
            || $before['schedule_template_id'] !== (int) $course->schedule_template_id
            || $before['academic_hours'] !== (int) $course->academic_hours
            || ($course->isExtracurricular() && $before['end_date'] !== $course->end_date?->toDateString())
            || $before['period_id'] !== (int) $course->period_id
            || $before['schedule_starts_at'] !== CoursePlanner::normalizeTime($course->scheduleTemplate?->starts_at)
            || $before['schedule_ends_at'] !== CoursePlanner::normalizeTime($course->scheduleTemplate?->ends_at)
            || $before['schedule_days'] !== json_encode($course->scheduleTemplate?->days ?? [])
            || $this->sessionTimesDifferFromSchedule($course);
    }

    private function sessionTimesDifferFromSchedule(Course $course): bool
    {
        $course->loadMissing('scheduleTemplate', 'managedGroup.sessions');
        $schedule = $course->scheduleTemplate;
        $firstSession = $course->managedGroup?->sessions
            ?->sortBy('session_date')
            ?->sortBy('starts_at')
            ?->first();

        if (! $schedule || ! $firstSession) {
            return false;
        }

        return CoursePlanner::normalizeTime($firstSession->starts_at) !== CoursePlanner::normalizeTime($schedule->starts_at)
            || CoursePlanner::normalizeTime($firstSession->ends_at) !== CoursePlanner::normalizeTime($schedule->ends_at);
    }
}
