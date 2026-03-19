<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\Enrollment;
use Illuminate\Console\Command;

class BackfillChargeAcademicContext extends Command
{
    protected $signature = 'finance:backfill-charge-context {--dry-run : Solo muestra conteos sin persistir cambios}';

    protected $description = 'Rellena enrollment, course, group y period en cargos históricos.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $unlinked = 0;

        Charge::query()->orderBy('id')->chunkById(200, function ($charges) use (&$updated, &$unlinked, $dryRun): void {
            foreach ($charges as $charge) {
                $enrollment = Enrollment::query()
                    ->with(['group.course'])
                    ->where('student_id', $charge->student_id)
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                    ->orderByDesc('enrolled_at')
                    ->orderByDesc('id')
                    ->first();

                if (! $enrollment) {
                    $unlinked++;
                    if (! $dryRun && ! $charge->origin) {
                        $charge->update([
                            'origin' => 'legacy_unlinked',
                        ]);
                    }
                    continue;
                }

                $payload = [
                    'enrollment_id' => $enrollment->id,
                    'group_id' => $enrollment->group_id,
                    'course_id' => $enrollment->group?->course_id,
                    'period_id' => $enrollment->group?->course?->period_id,
                    'origin' => $charge->origin ?: 'migration',
                ];

                $hasChanges = collect($payload)->contains(fn ($value, $key) => $charge->{$key} != $value);

                if ($hasChanges) {
                    $updated++;
                    if (! $dryRun) {
                        $charge->update($payload);
                    }
                }
            }
        });

        $this->info("Cargos actualizados: {$updated}");
        $this->info("Cargos sin vínculo académico: {$unlinked}");

        return self::SUCCESS;
    }
}
