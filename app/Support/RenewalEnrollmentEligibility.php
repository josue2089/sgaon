<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

final class RenewalEnrollmentEligibility
{
    /**
     * @return array{eligible: bool, latest_entry: ?GradeEntry, reason: string}
     */
    public static function evaluateForCourse(Student $student, Course $sourceCourse): array
    {
        $enrollmentIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->whereHas('group', fn ($query) => $query->where('course_id', $sourceCourse->id))
            ->pluck('id');

        if ($enrollmentIds->isEmpty()) {
            return [
                'eligible' => false,
                'latest_entry' => null,
                'reason' => 'missing_enrollment',
            ];
        }

        $latestEntry = GradeEntry::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->with('evaluationSet')
            ->get()
            ->sortByDesc(function (GradeEntry $entry) {
                $evaluatedOn = $entry->evaluationSet?->evaluated_on;

                return sprintf(
                    '%s-%010d',
                    $evaluatedOn ? $evaluatedOn->format('Ymd') : '00000000',
                    $entry->id
                );
            })
            ->first();

        if (! $latestEntry) {
            return [
                'eligible' => false,
                'latest_entry' => null,
                'reason' => 'missing_grade',
            ];
        }

        foreach (GradeRubric::skillToColumnMap() as $column) {
            $rating = (string) $latestEntry->getAttribute($column);
            if ($rating === GradeRubric::NEED_SUPPORT || ! in_array($rating, [
                GradeRubric::OUTSTANDING,
                GradeRubric::ACCEPTABLE,
                GradeRubric::REGULAR,
            ], true)) {
                return [
                    'eligible' => false,
                    'latest_entry' => $latestEntry,
                    'reason' => 'need_support',
                ];
            }
        }

        return [
            'eligible' => true,
            'latest_entry' => $latestEntry,
            'reason' => 'eligible',
        ];
    }

    /** Bloquea inscribir al siguiente nivel si la evaluación final del curso anterior fue Need Support. */
    public static function assertCanEnrollInGroup(Student $student, Group $targetGroup): void
    {
        $targetCourse = $targetGroup->course()->with(['programLevel', 'courseLevel'])->first();
        if (! $targetCourse) {
            return;
        }

        $candidatePreviousEnrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->with(['group.course.programLevel', 'group.course.courseLevel'])
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->get()
            ->first(function (Enrollment $enrollment) use ($targetCourse): bool {
                $sourceCourse = $enrollment->group?->course;
                if (! $sourceCourse || (int) $sourceCourse->id === (int) $targetCourse->id) {
                    return false;
                }

                if ($sourceCourse->programLevel && $targetCourse->program_level_id) {
                    return (int) ($sourceCourse->programLevel?->nextLevel()?->id ?? 0) === (int) $targetCourse->program_level_id;
                }

                if ($sourceCourse->courseLevel && $targetCourse->course_level_id) {
                    return (int) ($sourceCourse->courseLevel?->nextLevel()?->id ?? 0) === (int) $targetCourse->course_level_id;
                }

                return false;
            });

        if (! $candidatePreviousEnrollment || ! $candidatePreviousEnrollment->group?->course) {
            return;
        }

        $eligibility = RenewalEnrollmentEligibility::evaluateForCourse($student, $candidatePreviousEnrollment->group->course);
        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages([
                'student_id' => 'El alumno no puede inscribirse al siguiente curso porque su evaluación final está en Need Support.',
            ]);
        }
    }
}
