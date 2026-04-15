<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Program;
use App\Models\ProgramLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillProgramLevels extends Command
{
    protected $signature = 'programs:backfill-course-program-levels {--dry-run}';

    protected $description = 'Asigna program_id y program_level_id a cursos existentes usando nombre, codigo y course_level legado.';

    public function handle(): int
    {
        $programs = Program::query()->get()->keyBy('code');
        $levels = ProgramLevel::query()->with('program')->get();
        $updated = 0;
        $skipped = 0;

        Course::query()
            ->with(['program', 'programLevel', 'courseLevel'])
            ->whereNull('program_level_id')
            ->orderBy('id')
            ->get()
            ->each(function (Course $course) use ($programs, $levels, &$updated, &$skipped): void {
                $match = $this->resolveProgramLevel($course, $programs, $levels);
                if (! $match) {
                    $skipped++;
                    $this->warn("Sin match: {$course->id} | {$course->name}");

                    return;
                }

                $updated++;
                if ($this->option('dry-run')) {
                    $this->line("DRY RUN: {$course->id} -> {$match->program->name} / {$match->name}");

                    return;
                }

                $course->forceFill([
                    'program_id' => $match->program_id,
                    'program_level_id' => $match->id,
                ])->save();
            });

        $this->info("Backfill programas completado. Actualizados: {$updated}. Sin match: {$skipped}.");

        return self::SUCCESS;
    }

    private function resolveProgramLevel(Course $course, $programs, $levels): ?ProgramLevel
    {
        if ($course->program_level_id) {
            return $levels->firstWhere('id', $course->program_level_id);
        }

        $haystack = Str::lower(trim(($course->name ?? '').' '.($course->code ?? '').' '.($course->courseLevel?->name ?? '').' '.($course->courseLevel?->code ?? '')));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $haystack) ?? '';

        $programCode = str_contains($normalized, 'pre primary') ? 'PREP' : (str_contains($normalized, 'primary') ? 'PRIMARY' : (str_contains($normalized, 'high school') || str_contains($normalized, 'hs') || str_contains($normalized, 'conversational') ? 'HS' : null));
        if (! $programCode || ! $programs->has($programCode)) {
            return null;
        }

        $program = $programs->get($programCode);

        foreach ($levels->where('program_id', $program->id) as $level) {
            if (str_contains($normalized, Str::lower($level->name)) || str_contains(str_replace(' ', '', $normalized), Str::lower(str_replace(' ', '', $level->code)))) {
                return $level;
            }
        }

        if ($course->courseLevel) {
            return $levels->where('program_id', $program->id)->firstWhere('sort_order', $course->courseLevel->scale_position);
        }

        return null;
    }
}
