<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReportManagedGroupDuplicates extends Command
{
    protected $signature = 'groups:report-duplicates {--csv : Escribe también un CSV bajo storage/app/reports/}';

    protected $description = 'Lista cursos con más de un Group asociado (sin modificar datos). Sirve para que el equipo elimine manualmente en la UI.';

    public function handle(): int
    {
        $duplicates = Group::query()
            ->selectRaw('course_id, COUNT(*) as total')
            ->whereNotNull('course_id')
            ->groupBy('course_id')
            ->having('total', '>', 1)
            ->pluck('total', 'course_id');

        if ($duplicates->isEmpty()) {
            $this->info('No hay cursos con grupos duplicados.');

            return self::SUCCESS;
        }

        $rows = [];
        $courses = Course::with(['campus', 'managedGroup'])
            ->whereIn('id', $duplicates->keys())
            ->get()
            ->keyBy('id');

        foreach ($duplicates as $courseId => $total) {
            $course = $courses->get($courseId);
            $groups = Group::withCount(['enrollments', 'sessions'])
                ->where('course_id', $courseId)
                ->orderBy('id')
                ->get();

            $canonical = $groups->sortByDesc(fn ($g) => ($g->enrollments_count * 1000) + $g->sessions_count)->first();

            $row = [
                'course_id' => $courseId,
                'course_name' => $course?->name ?? $course?->code ?? '(desconocido)',
                'campus' => $course?->campus?->name ?? '—',
                'total_grupos' => (int) $total,
                'group_ids' => $groups->pluck('id')->implode('|'),
                'managed_group_id_actual' => $course?->managed_group_id,
                'sugerencia_canonico' => $canonical?->id,
                'detalle' => $groups
                    ->map(fn ($g) => sprintf(
                        '#%d name="%s" enrollments=%d sesiones=%d teacher=%s',
                        $g->id,
                        (string) $g->name,
                        $g->enrollments_count,
                        $g->sessions_count,
                        $g->teacher_id ?? '—',
                    ))
                    ->implode(' || '),
            ];

            $rows[] = $row;
            $this->line(sprintf(
                'Curso #%d "%s" (%s) → %d grupos [ids: %s] · sugerido canónico: #%s',
                $row['course_id'],
                $row['course_name'],
                $row['campus'],
                $row['total_grupos'],
                $row['group_ids'],
                $row['sugerencia_canonico'] ?? '—',
            ));
        }

        $this->newLine();
        $this->info('Total de cursos con grupos duplicados: '.count($rows));
        $this->warn('Este comando NO elimina nada. Revisa cada caso y elimina el sobrante desde la UI.');

        if ($this->option('csv')) {
            $filename = 'reports/managed-group-duplicates-'.now()->format('Ymd-His').'.csv';
            $handle = fopen('php://temp', 'w+');
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            rewind($handle);
            Storage::disk('local')->put($filename, stream_get_contents($handle));
            fclose($handle);
            $this->info("CSV escrito en: storage/app/{$filename}");
        }

        return self::SUCCESS;
    }
}
