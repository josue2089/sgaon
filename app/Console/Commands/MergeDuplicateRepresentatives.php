<?php

namespace App\Console\Commands;

use App\Services\RepresentativeMerger;
use Illuminate\Console\Command;

/** #472: une los representantes duplicados para que cada persona tenga una sola ficha y una sola cuenta. */
class MergeDuplicateRepresentatives extends Command
{
    protected $signature = 'representatives:merge-duplicates
        {--dry-run : Muestra qué se uniría sin guardar}
        {--list : Muestra los nombres de cada grupo}';

    protected $description = 'Une representantes con la misma cédula o correo y nombre compatible';

    public function handle(RepresentativeMerger $merger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        ['merge' => $merge, 'review' => $review] = $merger->plan();

        $rows = 0;
        foreach ($merge as $cluster) {
            $rows += $cluster->count() - 1;
            if ($this->option('list')) {
                $this->line('Unir: '.$cluster->map(fn ($rep) => "#{$rep->id} {$rep->full_name}")->implode(' + '));
            }
            if (! $dryRun) {
                $merger->merge($cluster);
            }
        }

        $this->info(($dryRun ? 'Se unirían ' : 'Se unieron ').count($merge)." grupos ({$rows} fichas menos).");

        if ($review !== []) {
            $this->warn(count($review).' grupos para revisar a mano (no se tocan):');
            $this->table(['Motivo', 'Representantes'], collect($review)
                ->map(fn (array $item) => [$item['reason'], implode(', ', $item['ids'])])
                ->all());
        }
        if ($dryRun) {
            $this->info('[dry-run] No se guardó nada.');
        }

        return self::SUCCESS;
    }
}
