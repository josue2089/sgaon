<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Course;
use App\Support\CoursePlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cambia la fecha de inicio de un curso y regenera su calendario (las clases con asistencia se respetan).
 * Con --dry-run muestra las clases antes y después y deshace todo. Ticket DevTeam #306.
 */
class SetCourseStartDate extends Command
{
    protected $signature = 'courses:set-start-date {course : ID del curso} {date : Nueva fecha de inicio (AAAA-MM-DD)} {--dry-run : Solo muestra el resultado, sin guardar}';

    protected $description = 'Cambia la fecha de inicio de un curso y regenera su calendario respetando las clases con asistencia.';

    public function handle(): int
    {
        $course = Course::query()->find((int) $this->argument('course'));
        if (! $course) {
            $this->error('No existe el curso.');

            return self::FAILURE;
        }

        $newDate = Carbon::parse((string) $this->argument('date'))->startOfDay();
        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';

        $this->line("Curso #{$course->id}: {$course->name}");
        $this->line('Inicio actual: '.$course->start_date?->format('d/m/Y').' · fin: '.$course->end_date?->format('d/m/Y'));
        $before = $this->snapshot($course);

        DB::beginTransaction();
        try {
            $course->update(['start_date' => $newDate->toDateString()]);
            $course = CoursePlanner::sync($course->fresh(['teacher', 'period', 'scheduleTemplate', 'managedGroup.sessions.attendanceRecords']), true);
            $after = $this->snapshot($course);

            $lostAttendance = $before->filter(fn ($row) => $row['attendance'] > 0)->keys()->diff($after->keys());
            if ($lostAttendance->isNotEmpty()) {
                throw new \RuntimeException('Se perderían clases con asistencia: '.$lostAttendance->implode(', '));
            }

            $this->line($prefix.'Nuevo inicio: '.$course->start_date?->format('d/m/Y').' · fin: '.$course->end_date?->format('d/m/Y'));
            $this->table(['Antes', 'Asist.', 'Después', 'Asist.'], $this->compare($before, $after));

            if ($dryRun) {
                DB::rollBack();
                $this->info('[dry-run] No se guardó nada.');

                return self::SUCCESS;
            }

            AuditLog::create([
                'action' => 'course.start_date_changed',
                'auditable_type' => Course::class,
                'auditable_id' => $course->id,
                'payload' => ['from' => $before->keys()->first(), 'to' => $newDate->toDateString(), 'via' => 'courses:set-start-date'],
            ]);
            DB::commit();
            $this->info('Fecha de inicio actualizada.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->error('No se aplicó: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return \Illuminate\Support\Collection<string, array{id:int, attendance:int}> fecha => datos, por sesión del grupo. */
    private function snapshot(Course $course)
    {
        return ClassSession::query()
            ->where('group_id', $course->managed_group_id)
            ->withCount('attendanceRecords')
            ->orderBy('session_date')
            ->get()
            ->mapWithKeys(fn (ClassSession $session) => [
                $session->session_date->toDateString() => ['id' => $session->id, 'attendance' => (int) $session->attendance_records_count],
            ]);
    }

    private function compare($before, $after): array
    {
        $rows = [];
        $b = $before->keys()->values();
        $a = $after->keys()->values();
        for ($i = 0; $i < max($b->count(), $a->count()); $i++) {
            $rows[] = [
                isset($b[$i]) ? Carbon::parse($b[$i])->format('d/m/Y') : '',
                isset($b[$i]) ? $before[$b[$i]]['attendance'] : '',
                isset($a[$i]) ? Carbon::parse($a[$i])->format('d/m/Y') : '',
                isset($a[$i]) ? $after[$a[$i]]['attendance'] : '',
            ];
        }

        return $rows;
    }
}
