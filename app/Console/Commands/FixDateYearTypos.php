<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Support\CoursePlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Corrige fechas guardadas con el año de dos dígitos (0026 en vez de 2026) y regenera el calendario
 * de los cursos afectados. Ticket DevTeam #259.
 */
class FixDateYearTypos extends Command
{
    /** Tabla => columnas de fecha que se revisan. */
    private const COLUMNS = [
        'courses' => ['start_date', 'end_date'],
        'groups' => ['start_date', 'end_date'],
        'class_sessions' => ['session_date'],
        'enrollments' => ['enrolled_at'],
        'students' => ['enrollment_date'],
        'charges' => ['due_date'],
        'payments' => ['paid_at'],
    ];

    protected $signature = 'data:fix-date-year-typos {--dry-run : Solo lista las fechas, sin corregirlas}';

    protected $description = 'Corrige fechas con año 00xx (p. ej. 0026 → 2026) y regenera las clases de los cursos afectados.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';
        $fixes = 0;
        $courseIds = collect();

        DB::transaction(function () use ($dryRun, $prefix, &$fixes, &$courseIds) {
            foreach (self::COLUMNS as $table => $columns) {
                foreach ($columns as $column) {
                    $rows = DB::table($table)->select('id', $column)->whereNotNull($column)->where($column, '<', '1000-01-01')->get();
                    foreach ($rows as $row) {
                        $old = substr((string) $row->{$column}, 0, 10);
                        $new = Carbon::parse($old)->addYears(2000)->toDateString();
                        $this->line("{$prefix}{$table} #{$row->id} {$column}: {$old} → {$new}");
                        $fixes++;
                        if (! $dryRun) {
                            DB::table($table)->where('id', $row->id)->update([$column => $new]);
                        }
                        if ($table === 'courses') {
                            $courseIds->push($row->id);
                        } elseif ($table === 'groups') {
                            $courseIds->push(DB::table('groups')->where('id', $row->id)->value('course_id'));
                        }
                    }
                }
            }

            foreach ($courseIds->filter()->unique() as $courseId) {
                $course = Course::query()->find($courseId);
                if (! $course) {
                    continue;
                }
                $this->line("{$prefix}Calendario regenerado: curso #{$course->id} {$course->name}");
                if (! $dryRun) {
                    CoursePlanner::sync($course->fresh(['teacher', 'period', 'scheduleTemplate', 'managedGroup.sessions.attendanceRecords']), true);
                }
            }
        });

        $this->info("{$prefix}Fechas corregidas: {$fixes}");

        return self::SUCCESS;
    }
}
