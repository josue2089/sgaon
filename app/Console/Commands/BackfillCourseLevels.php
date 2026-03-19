<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillCourseLevels extends Command
{
    protected $signature = 'levels:backfill-course-levels {--dry-run}';

    protected $description = 'Asigna course_level_id a cursos existentes usando nombre, codigo y contexto academico.';

    public function handle(): int
    {
        $levels = CourseLevel::query()->where('status', 'active')->get()->keyBy('code');
        $updated = 0;
        $skipped = 0;

        Course::query()
            ->with(['level'])
            ->whereNull('course_level_id')
            ->orderBy('id')
            ->get()
            ->each(function (Course $course) use ($levels, &$updated, &$skipped): void {
                $match = $this->resolveLevelCode($course);

                if (! $match || ! $levels->has($match)) {
                    $skipped++;
                    $this->warn("Sin match: {$course->id} | {$course->name} | {$course->code}");

                    return;
                }

                $level = $levels->get($match);
                $updated++;

                if ($this->option('dry-run')) {
                    $this->line("DRY RUN: {$course->id} -> {$level->code} ({$level->name})");

                    return;
                }

                $course->forceFill(['course_level_id' => $level->id])->save();
                $this->info("Asignado: {$course->id} -> {$level->code}");
            });

        $this->info("Backfill course levels completado. Actualizados: {$updated}. Sin match: {$skipped}.");

        return self::SUCCESS;
    }

    private function resolveLevelCode(Course $course): ?string
    {
        $haystack = Str::lower(trim(($course->name ?? '').' '.($course->code ?? '').' '.($course->level?->name ?? '').' '.($course->level?->code ?? '')));

        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $haystack) ?? '';

        if (preg_match('/(?:high school|hs)\s*([1-6])\b/', $normalized, $matches)) {
            return 'HS'.$matches[1];
        }

        if (preg_match('/(?:primary)\s*([1-6])\b/', $normalized, $matches)) {
            return 'P'.$matches[1];
        }

        if (preg_match('/\bp([1-6])\b/', $normalized, $matches)) {
            return 'P'.$matches[1];
        }

        if (preg_match('/\bhs([1-6])\b/', str_replace(' ', '', $normalized), $matches)) {
            return 'HS'.$matches[1];
        }

        return null;
    }
}
