<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

/** Alumnos activos con lo necesario para calcular su acceso al portal. La usan la pantalla y el comando. #472 */
final class PortalAccessDirectory
{
    /** @param  array{campus_id?: ?int, program_id?: ?int, course_id?: ?int}  $filters */
    public function students(array $filters = []): Builder
    {
        return Student::query()
            ->where('status', 'active')
            ->with([
                'user.portalInvitations',
                'representatives.user.portalInvitations',
                'enrollments.group.course.program',
                'registrationProgram',
            ])
            ->when($filters['campus_id'] ?? null, fn (Builder $query, $campusId) => $query->where('campus_id', $campusId))
            ->when($filters['program_id'] ?? null, fn (Builder $query, $programId) => $query->where(fn (Builder $inner) => $inner
                ->where('registration_program_id', $programId)
                ->orWhereHas('enrollments', fn (Builder $enrollments) => $enrollments
                    ->where('status', 'active')
                    ->whereHas('group.course', fn (Builder $course) => $course->where('program_id', $programId)))))
            ->when($filters['course_id'] ?? null, fn (Builder $query, $courseId) => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                ->where('status', 'active')
                ->whereHas('group', fn (Builder $group) => $group->where('course_id', $courseId))));
    }
}
