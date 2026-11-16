<?php

namespace App\Support;

use App\Models\ClassSession;
use App\Models\Course;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Simula un cambio de calendario de un curso (fecha de inicio, horario, período…) y deshace todo.
 * Devuelve las clases antes y después y las que tienen asistencia y se perderían. #306 (comando) y #444 (pantalla).
 */
final class CourseCalendarPreview
{
    /**
     * @param  callable(Course): Course  $apply  aplica el cambio sobre una copia fresca del curso y devuelve el curso ya sincronizado
     * @return array{before: Collection<string, array{id:int, attendance:int}>, after: Collection<string, array{id:int, attendance:int}>, lost: Collection<int, string>, changed: bool, rows: list<array{before: ?string, before_attendance: ?int, after: ?string}>, start_after: ?string, end_after: ?string}
     */
    public static function simulate(Course $course, callable $apply): array
    {
        $before = self::snapshot((int) $course->managed_group_id);

        DB::beginTransaction();
        try {
            $updated = $apply(Course::query()->findOrFail($course->id));
            $after = self::snapshot((int) $updated->managed_group_id);
            $startAfter = $updated->start_date?->toDateString();
            $endAfter = $updated->end_date?->toDateString();
        } finally {
            DB::rollBack();
        }

        return self::compare($before, $after) + ['start_after' => $startAfter, 'end_after' => $endAfter];
    }

    /** @return Collection<string, array{id:int, attendance:int}> fecha => datos de la sesión */
    public static function snapshot(int $groupId): Collection
    {
        if ($groupId <= 0) {
            return collect();
        }

        return ClassSession::query()
            ->where('group_id', $groupId)
            ->withCount('attendanceRecords')
            ->orderBy('session_date')
            ->get()
            ->mapWithKeys(fn (ClassSession $session) => [
                $session->session_date->toDateString() => ['id' => $session->id, 'attendance' => (int) $session->attendance_records_count],
            ]);
    }

    /**
     * @param  Collection<string, array{id:int, attendance:int}>  $before
     * @param  Collection<string, array{id:int, attendance:int}>  $after
     */
    public static function compare(Collection $before, Collection $after): array
    {
        $lost = $before->filter(fn (array $row) => $row['attendance'] > 0)->keys()->diff($after->keys())->values();
        $b = $before->keys()->values();
        $a = $after->keys()->values();
        $rows = [];
        for ($i = 0; $i < max($b->count(), $a->count()); $i++) {
            $rows[] = [
                'before' => $b[$i] ?? null,
                'before_attendance' => isset($b[$i]) ? $before[$b[$i]]['attendance'] : null,
                'after' => $a[$i] ?? null,
            ];
        }

        return [
            'before' => $before,
            'after' => $after,
            'lost' => $lost,
            'changed' => $b->all() !== $a->all(),
            'rows' => $rows,
        ];
    }

    public static function formatDates(Collection $dates): string
    {
        return $dates->map(fn (string $date) => Carbon::parse($date)->format('d/m/Y'))->implode(', ');
    }
}
