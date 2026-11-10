<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesCampusAccess;
use App\Models\AcademicLevel;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Period;
use App\Models\ProgramLevel;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EnrollmentBillingService;
use App\Services\LevelRenewalService;
use App\Support\AuditTrail;
use App\Support\CoursePlanner;
use App\Support\RenewalEnrollmentEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** "Abrir siguiente nivel": crea el curso que sigue con sus alumnos en un solo paso. #442 */
class CourseNextLevelController extends Controller
{
    use ScopesCampusAccess;

    public function create(Course $course, LevelRenewalService $renewals): View|RedirectResponse
    {
        $course->load(['programLevel.program', 'teacher', 'scheduleTemplate', 'period', 'managedGroup.enrollments.student']);
        $nextLevel = $course->programLevel?->nextLevel();
        if (! $nextLevel) {
            return redirect()->route('courses.show', $course)->withErrors(['next_level' => 'Este nivel no tiene un siguiente nivel configurado.']);
        }

        $existing = $renewals->nextCourseFor($course, sameScheduleOnly: true);
        if ($existing?->teacher_id) {
            return redirect()->route('courses.show', $existing)->with('info', 'El siguiente nivel ya está abierto.');
        }

        $students = $course->managedGroup?->enrollments
            ->where('status', 'active')
            ->filter(fn (Enrollment $enrollment) => $enrollment->student)
            ->map(fn (Enrollment $enrollment) => ['student' => $enrollment->student] + $this->promotion($enrollment->student, $course))
            ->sortBy(fn (array $row) => $row['student']->full_name)
            ->values() ?? collect();

        return view('courses.next-level', [
            'course' => $course,
            'nextLevel' => $nextLevel->load('program'),
            'levels' => ProgramLevel::query()->with('program')->where('status', 'active')->orderBy('program_id')->orderBy('sort_order')->get(),
            'teachers' => Teacher::query()->where('campus_id', $course->campus_id)->orderBy('first_name')->get(),
            'schedules' => ScheduleTemplate::query()->where('campus_id', $course->campus_id)->where('status', 'active')->get(),
            'periods' => Period::query()->where('campus_id', $course->campus_id)->where('status', 'active')->orderBy('code')->get(),
            'startDate' => ($course->end_date?->copy()->addDay() ?? today())->toDateString(),
            'students' => $students,
        ]);
    }

    public function store(Request $request, Course $course, LevelRenewalService $renewals, EnrollmentBillingService $billing): RedirectResponse
    {
        $course->load('managedGroup.enrollments');
        $data = $request->validate([
            'program_level_id' => ['required', Rule::exists('program_levels', 'id')],
            'teacher_id' => ['required', Rule::exists('teachers', 'id')->where('campus_id', $course->campus_id)],
            'schedule_template_id' => ['required', Rule::exists('schedule_templates', 'id')->where('campus_id', $course->campus_id)],
            'period_id' => ['required', Rule::exists('periods', 'id')->where('campus_id', $course->campus_id)],
            'start_date' => ['required', 'date', 'sane_date'],
            'academic_hours' => ['required', 'integer', 'min:1', 'max:500'],
            'student_ids' => ['array'],
            'student_ids.*' => ['integer'],
        ]);

        $level = ProgramLevel::query()->with('program')->findOrFail($data['program_level_id']);
        $activeIds = $course->managedGroup?->enrollments->where('status', 'active')->pluck('student_id')->map(fn ($id) => (int) $id) ?? collect();
        $selected = Student::query()->whereKey(collect($data['student_ids'] ?? [])->map(fn ($id) => (int) $id)->intersect($activeIds))->get();

        [$next, $enrolled, $blocked] = DB::transaction(function () use ($course, $data, $level, $selected, $renewals, $billing, $request) {
            $next = $this->openCourse($course, $data, $level, $renewals);

            $enrolled = 0;
            $blocked = [];
            foreach ($selected as $student) {
                if ($this->promotion($student, $course)['blocked']) {
                    $blocked[] = $student->full_name;

                    continue;
                }
                $enrollment = Enrollment::updateOrCreate(
                    ['group_id' => $next->managed_group_id, 'student_id' => $student->id],
                    ['campus_id' => $next->campus_id, 'enrolled_at' => $data['start_date'], 'status' => 'active', 'progress' => 0],
                );
                $billing->createTuitionCharge($enrollment, $request);
                $enrolled++;
            }

            AuditTrail::log($request, 'course.next_level_open', $next, ['from_course_id' => $course->id, 'students' => $enrolled, 'blocked' => $blocked]);

            return [$next, $enrolled, $blocked];
        });

        $redirect = redirect()->route('courses.show', $next)->with('success', "Siguiente nivel abierto. Alumnos inscritos: {$enrolled}.");

        return $blocked === [] ? $redirect : $redirect->with('warning', 'No se inscribió por evaluación Need Support: '.implode(', ', $blocked).'.');
    }

    /**
     * Quien abre el nivel es el admin: solo Need Support bloquea. Sin evaluación final (el curso puede
     * no haber terminado) se avisa y queda a su criterio.
     *
     * @return array{blocked: bool, pending_grade: bool}
     */
    private function promotion(Student $student, Course $course): array
    {
        $result = RenewalEnrollmentEligibility::evaluateForCourse($student, $course);

        return [
            'blocked' => ! $result['eligible'] && $result['reason'] !== 'missing_grade',
            'pending_grade' => $result['reason'] === 'missing_grade',
        ];
    }

    /** Si el recordatorio ya dejó un borrador de ese nivel y horario, se completa en lugar de duplicarlo. */
    private function openCourse(Course $course, array $data, ProgramLevel $level, LevelRenewalService $renewals): Course
    {
        $draft = $renewals->nextCourseFor($course, sameScheduleOnly: true);
        $useDraft = $draft && ! $draft->teacher_id
            && (int) $draft->program_level_id === (int) $level->id
            && (int) $draft->schedule_template_id === (int) $data['schedule_template_id'];

        $attributes = [
            'campus_id' => $course->campus_id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $course->campus_id, 'name' => $level->program->name])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'course_level_id' => null,
            'teacher_id' => $data['teacher_id'],
            'period_id' => $data['period_id'],
            'schedule_template_id' => $data['schedule_template_id'],
            'start_date' => $data['start_date'],
            'academic_hours' => $data['academic_hours'],
            'end_date' => null,
            'status' => 'active',
        ];

        $next = $useDraft ? $draft : new Course;
        $next->fill($attributes);
        $next->setRelation('programLevel', $level);
        $next->setRelation('teacher', Teacher::query()->find($data['teacher_id']));
        $next->setRelation('scheduleTemplate', ScheduleTemplate::query()->find($data['schedule_template_id']));
        $next->name = $next->buildStructuredName();
        $next->save();

        return CoursePlanner::sync($next->load(['teacher', 'period', 'scheduleTemplate', 'managedGroup.sessions.attendanceRecords']))->fresh();
    }
}
