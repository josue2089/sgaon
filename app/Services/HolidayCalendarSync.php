<?php

namespace App\Services;

use App\Models\Course;
use App\Support\CoursePlanner;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class HolidayCalendarSync
{
    /**
     * Recalcula el calendario de los cursos activos afectados por un feriado.
     * $campusId null = feriado global (todas las sedes).
     *
     * @return array{updated: int, skipped: array<string, string>, conflicts: array<string, array<int, string>>}
     */
    public function resyncForHoliday(?int $campusId, ?Carbon $fromDate = null): array
    {
        $fromDate ??= now()->startOfYear();

        $courses = Course::query()
            ->where('status', 'active')
            ->whereNotNull('schedule_template_id')
            ->whereNotNull('start_date')
            ->whereNotNull('academic_hours')
            ->when($campusId, fn (Builder $q) => $q->where('campus_id', $campusId))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $fromDate->toDateString()))
            ->orderBy('id')
            ->get();

        return $this->resyncCourses($courses);
    }

    /**
     * @return array{updated: int, skipped: array<string, string>, conflicts: array<string, array<int, string>>}
     */
    public function resyncCourse(Course $course): array
    {
        if (! $course->schedule_template_id || ! $course->start_date || ! $course->academic_hours) {
            return [
                'updated' => 0,
                'skipped' => [$this->label($course) => 'El curso no tiene horario, fecha de inicio u horas académicas.'],
                'conflicts' => [],
            ];
        }

        return $this->resyncCourses(collect([$course]));
    }

    /**
     * @param  iterable<Course>  $courses
     */
    private function resyncCourses(iterable $courses): array
    {
        $result = ['updated' => 0, 'skipped' => [], 'conflicts' => []];

        foreach ($courses as $course) {
            try {
                $course = CoursePlanner::sync($course, true);
                $result['updated']++;
            } catch (ValidationException $exception) {
                $result['skipped'][$this->label($course)] = collect($exception->errors())->flatten()->first()
                    ?? 'No se pudo recalcular.';

                continue;
            }

            $conflicts = CoursePlanner::sessionsOnHolidays($course, onlyProtected: true);
            if ($conflicts->isNotEmpty()) {
                $result['conflicts'][$this->label($course)] = $conflicts
                    ->map(fn ($session) => $session->session_date->format('d/m/Y'))
                    ->all();
            }
        }

        return $result;
    }

    /**
     * @param  array{updated: int, skipped: array<string, string>, conflicts: array<string, array<int, string>>}  $result
     * @return array<string, string>
     */
    public static function flashMessages(array $result, string $prefix): array
    {
        $messages = [
            'success' => trim($prefix.' Se recalcularon '.$result['updated'].' curso(s).'),
        ];

        $warnings = [];
        foreach ($result['conflicts'] as $course => $dates) {
            $warnings[] = $course.': '.implode(', ', $dates);
        }
        if ($warnings !== []) {
            $messages['warning'] = 'Estas sesiones tienen asistencia cargada en un feriado y no se movieron (revísalas desde la ficha del curso): '.implode(' · ', $warnings);
        }

        if ($result['skipped'] !== []) {
            $skipped = [];
            foreach ($result['skipped'] as $course => $reason) {
                $skipped[] = $course.' ('.$reason.')';
            }
            $messages['info'] = 'No se recalcularon: '.implode(' · ', $skipped);
        }

        return $messages;
    }

    private function label(Course $course): string
    {
        return (string) ($course->name ?: $course->code ?: 'Curso #'.$course->id);
    }
}
