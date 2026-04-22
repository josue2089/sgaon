<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\Student;

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
}
