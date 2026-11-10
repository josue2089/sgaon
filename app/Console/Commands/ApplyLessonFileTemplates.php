<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\ProgramLevelLesson;
use App\Support\CoursePlanner;
use App\Support\LessonTemplates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * #470: carga el plan de clases oficial (plantillas File A/B) en cada subnivel y la estructura de clase de cada programa.
 *
 * Solo reemplaza las clases de relleno ("Unidad N" / "Contenido base de la clase N") y las que ya vinieron de la plantilla;
 * las editadas a mano se respetan salvo con --overwrite. Al terminar actualiza el plan en las sesiones de los cursos activos.
 */
class ApplyLessonFileTemplates extends Command
{
    protected $signature = 'program-lessons:apply-file-templates
        {--dry-run : Muestra qué cambiaría sin guardar}
        {--overwrite : Reemplaza también las clases editadas a mano}
        {--level= : Solo este subnivel (código, ej. HS3A)}';

    protected $description = 'Carga las 20 clases de cada subnivel según la plantilla File A/B de su serie';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');
        $summary = [];
        $kept = [];
        $withoutTemplate = [];
        $touchedLevelIds = [];

        DB::beginTransaction();
        try {
            $frameworks = $this->applyFrameworks();

            $levels = ProgramLevel::query()
                ->with(['program', 'lessons'])
                ->when($this->option('level'), fn ($query, $code) => $query->where('code', strtoupper($code)))
                ->orderBy('program_id')
                ->orderBy('sort_order')
                ->get();

            foreach ($levels as $level) {
                $series = LessonTemplates::seriesFor($level);
                $part = LessonTemplates::partFor($level);
                if (! $series || ! $part) {
                    if ($level->program && ! $level->program->is_extracurricular) {
                        $withoutTemplate[] = $level->code ?: $level->name;
                    }

                    continue;
                }

                $created = $updated = 0;
                foreach (LessonTemplates::lessons($series, $part) as $row) {
                    $lesson = $level->lessons->firstWhere('class_number', $row['class_number']);
                    if ($lesson && ! $overwrite && ! $lesson->from_template && ! $lesson->isPlaceholder()) {
                        $kept[] = [$level->code, $row['class_number'], $lesson->unit];

                        continue;
                    }

                    $values = $row + ['sort_order' => $row['class_number'], 'from_template' => true];
                    if (! $lesson) {
                        $level->lessons()->create($values);
                        $created++;

                        continue;
                    }

                    $lesson->fill($values);
                    if ($lesson->isDirty()) {
                        $lesson->save();
                        $updated++;
                    }
                }

                // Clases de relleno sobrantes (más allá de la 20) se quitan para que el plan quede en 20.
                $extra = $level->lessons->filter(fn (ProgramLevelLesson $lesson) => $lesson->class_number > 20 && $lesson->isPlaceholder());
                $extra->each->delete();

                if ($created || $updated || $extra->isNotEmpty()) {
                    $touchedLevelIds[] = $level->id;
                    $summary[] = [$level->code, LessonTemplates::seriesLabel($series).' '.$part, $created, $updated, $extra->count()];
                }
            }

            $summary === []
                ? $this->info('Sin cambios en las clases: el plan oficial ya está cargado.')
                : $this->table(['Subnivel', 'Plantilla', 'Creadas', 'Actualizadas', 'Relleno quitado'], $summary);
            foreach ($frameworks as $line) {
                $this->line($line);
            }
            foreach ($kept as [$code, $number, $unit]) {
                $this->warn("{$code} clase {$number}: se mantiene porque fue editada a mano (\"{$unit}\"). Usa --overwrite para reemplazarla.");
            }
            if ($withoutTemplate !== []) {
                $this->warn('Sin plantilla del cliente (no se tocan): '.implode(', ', $withoutTemplate));
            }

            $courses = $touchedLevelIds === [] ? collect() : Course::query()
                ->whereIn('program_level_id', $touchedLevelIds)
                ->where('status', 'active')
                ->get();
            $this->line('Cursos cuyo plan de sesiones se actualiza: '.$courses->count());

            if ($dryRun) {
                DB::rollBack();
                $this->info('[dry-run] No se guardó nada.');

                return self::SUCCESS;
            }

            foreach ($courses as $course) {
                CoursePlanner::refreshPlannedContent($course);
            }

            if ($summary !== [] || $frameworks !== []) {
                AuditLog::create([
                    'action' => 'program_lessons.file_templates_applied',
                    'payload' => ['levels' => $summary, 'kept' => $kept, 'courses' => $courses->count(), 'overwrite' => $overwrite, 'via' => 'program-lessons:apply-file-templates'],
                ]);
            }
            DB::commit();
            $this->info('Plan de clases oficial aplicado en '.count($summary).' subniveles.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error('No se aplicó: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return list<string> */
    private function applyFrameworks(): array
    {
        $lines = [];
        foreach (LessonTemplates::PROGRAM_FRAMEWORKS as $code => $key) {
            $program = Program::query()->where('code', $code)->first();
            if ($program && empty($program->class_framework)) {
                $program->update(['class_framework' => LessonTemplates::FRAMEWORKS[$key]]);
                $lines[] = "Estructura de clase cargada en {$program->name}.";
            }
        }

        return $lines;
    }
}
