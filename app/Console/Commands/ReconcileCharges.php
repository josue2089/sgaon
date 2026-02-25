<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Support\FinanceReconcile;
use Illuminate\Console\Command;

class ReconcileCharges extends Command
{
    protected $signature = 'finance:reconcile-charges {--campus_id=}';

    protected $description = 'Recalcula estado de cargos con base en pagos acumulados y vencimiento.';

    public function handle(): int
    {
        $query = Charge::query();
        $campusId = $this->option('campus_id');
        if (! empty($campusId)) {
            $query->where('campus_id', (int) $campusId);
        }

        $processed = 0;
        $updated = 0;

        $query->with('payments')->chunkById(200, function ($charges) use (&$processed, &$updated) {
            foreach ($charges as $charge) {
                $processed++;
                $before = $charge->status;
                FinanceReconcile::syncCharge($charge);
                if ($before !== $charge->status) {
                    $updated++;
                }
            }
        });

        $this->info("Conciliación completada. Procesados: {$processed}. Actualizados: {$updated}.");

        return self::SUCCESS;
    }
}
