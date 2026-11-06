<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Enrollment;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Support\AuditTrail;
use App\Support\RenewalEnrollmentEligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Paso al siguiente nivel: qué curso sigue, qué se le ofrece a la familia y la inscripción desde el portal. #442 */
class LevelRenewalService
{
    /** El curso siguiente puede empezar hasta estos días después de que termine el actual. */
    public const NEXT_COURSE_WINDOW_DAYS = 21;

    public function __construct(private readonly EnrollmentBillingService $billing)
    {
    }

    public function nextLevelOf(Course $course): ProgramLevel|CourseLevel|null
    {
        return $course->programLevel?->nextLevel() ?? $course->courseLevel?->nextLevel();
    }

    /** Curso activo del siguiente nivel en la misma sede; primero el de igual horario. */
    public function nextCourseFor(Course $course, bool $sameScheduleOnly = false): ?Course
    {
        $nextLevel = $this->nextLevelOf($course);
        if (! $nextLevel || ! $course->end_date) {
            return null;
        }

        return Course::query()
            ->with(['managedGroup', 'scheduleTemplate', 'period'])
            ->where('campus_id', $course->campus_id)
            ->whereKeyNot($course->id)
            ->where('status', 'active')
            ->where($nextLevel instanceof ProgramLevel ? 'program_level_id' : 'course_level_id', $nextLevel->id)
            ->whereDate('start_date', '>=', $course->end_date->toDateString())
            ->whereDate('start_date', '<=', $course->end_date->copy()->addDays(self::NEXT_COURSE_WINDOW_DAYS)->toDateString())
            ->when($sameScheduleOnly, fn ($query) => $query->where('schedule_template_id', $course->schedule_template_id))
            ->get()
            ->sortBy([
                fn (Course $a, Course $b) => (int) ($b->schedule_template_id === $course->schedule_template_id) <=> (int) ($a->schedule_template_id === $course->schedule_template_id),
                fn (Course $a, Course $b) => $a->start_date <=> $b->start_date,
            ])
            ->first();
    }

    /** @return Collection<int, array{source_course: Course, target_course: Course, already_enrolled: bool, eligible: bool, pending_grade: bool}> */
    public function offersFor(Student $student): Collection
    {
        return $student->enrollments()
            ->where('status', 'active')
            ->with(['group.course.programLevel', 'group.course.courseLevel'])
            ->get()
            ->map(function (Enrollment $enrollment) use ($student) {
                $course = $enrollment->group?->course;
                $target = $course ? $this->nextCourseFor($course) : null;
                if (! $target?->managedGroup) {
                    return null;
                }

                $eligibility = RenewalEnrollmentEligibility::evaluateForCourse($student, $course);

                return [
                    'source_course' => $course,
                    'target_course' => $target,
                    'already_enrolled' => Enrollment::query()
                        ->where('student_id', $student->id)
                        ->where('group_id', $target->managed_group_id)
                        ->where('status', 'active')
                        ->exists(),
                    'eligible' => $eligibility['eligible'],
                    'pending_grade' => $eligibility['reason'] === 'missing_grade',
                ];
            })
            ->filter()
            ->values();
    }

    /** Inscribe desde el portal con su mensualidad. Devuelve null si ya estaba inscrito. */
    public function renewFromPortal(Student $student, Course $target, Request $request): ?Enrollment
    {
        $group = $target->managedGroup;
        if ((int) $student->campus_id !== (int) $target->campus_id) {
            abort(403);
        }
        if (! $group || $target->status !== 'active') {
            throw ValidationException::withMessages(['renewal' => 'El curso de renovación aún no está disponible para inscripción.']);
        }

        return DB::transaction(function () use ($student, $target, $group, $request) {
            $seats = Enrollment::query()->where('group_id', $group->id)->where('status', 'active')->lockForUpdate();
            if ((clone $seats)->where('student_id', $student->id)->exists()) {
                return null;
            }

            $source = $this->sourceCourse($student, $target);
            if (! $source) {
                throw ValidationException::withMessages(['renewal' => 'No encontramos un curso previo válido para procesar la renovación.']);
            }
            if (! RenewalEnrollmentEligibility::evaluateForCourse($student, $source)['eligible']) {
                throw ValidationException::withMessages(['renewal' => 'La evaluación final no permite la inscripción automática al siguiente curso.']);
            }
            if ($group->capacity && (clone $seats)->count() >= (int) $group->capacity) {
                throw ValidationException::withMessages(['renewal' => 'Este curso ya no tiene cupos. Consulta con la administración.']);
            }

            $enrollment = Enrollment::updateOrCreate(
                ['student_id' => $student->id, 'group_id' => $group->id],
                [
                    'campus_id' => $student->campus_id,
                    'enrolled_at' => now()->toDateString(),
                    'renewed_from_portal_at' => now(),
                    'status' => 'active',
                    'progress' => 0,
                ],
            );
            $this->billing->createTuitionCharge($enrollment);
            AuditTrail::log($request, 'enrollment.portal_renewal', $enrollment, ['from_course_id' => $source->id, 'to_course_id' => $target->id]);

            return $enrollment;
        });
    }

    private function sourceCourse(Student $student, Course $target): ?Course
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->with(['group.course.programLevel', 'group.course.courseLevel'])
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Enrollment $enrollment) => $enrollment->group?->course)
            ->filter()
            ->first(function (Course $source) use ($target): bool {
                if ((int) $source->id === (int) $target->id) {
                    return false;
                }
                if ($source->programLevel && $target->program_level_id) {
                    return (int) ($source->programLevel->nextLevel()?->id ?? 0) === (int) $target->program_level_id;
                }
                if ($source->courseLevel && $target->course_level_id) {
                    return (int) ($source->courseLevel->nextLevel()?->id ?? 0) === (int) $target->course_level_id;
                }

                return false;
            });
    }
}
