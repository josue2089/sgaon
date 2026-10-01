<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Support\AlertEngine;
use App\Support\FinanceReconcile;
use Illuminate\Console\Command;

class VoidLegacyRecurringCharges extends Command
{
    public const VOID_STATUS = 'void';

    public const VOID_REASON = 'Cargo legacy 75$ no corresponde';

    protected $signature = 'finance:void-legacy-recurring-charges {--dry-run : Solo lista los cargos, sin modificarlos}';

    protected $description = 'Anula los cargos de 75$ creados por el comando legacy finance:generate-recurring-charges.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $charges = Charge::query()
            ->with('student')
            ->whereNull('voided_at')
            ->whereNull('enrollment_id')
            ->where('notes', 'like', 'Auto-generado por inscripción activa #%')
            ->where('concept', 'like', 'Mensualidad académica %')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $toVoid = [];
        $withPayments = [];

        foreach ($charges as $charge) {
            $paid = FinanceReconcile::paidTotalForCharge($charge);
            $row = [
                $charge->id,
                $charge->student_id,
                trim(($charge->student?->first_name ?? '').' '.($charge->student?->last_name ?? '')),
                $charge->concept,
                $charge->amount,
                $charge->status,
                $charge->due_date?->toDateString(),
                $paid,
            ];

            if ($paid > 0) {
                $withPayments[] = $row;
            } else {
                $toVoid[] = $row;
            }
        }

        $headers = ['ID', 'Alumno ID', 'Alumno', 'Concepto', 'Monto', 'Estado', 'Vence', 'Pagado'];

        $this->info(($dryRun ? '[dry-run] ' : '').'Cargos legacy sin pagos a anular: '.count($toVoid));
        if ($toVoid !== []) {
            $this->table($headers, $toVoid);
        }

        $this->warn('Cargos legacy con pagos (revisar manualmente, no se anulan): '.count($withPayments));
        if ($withPayments !== []) {
            $this->table($headers, $withPayments);
        }

        if ($dryRun || $toVoid === []) {
            return self::SUCCESS;
        }

        $ids = array_column($toVoid, 0);
        $studentIds = [];

        Charge::query()->whereIn('id', $ids)->each(function (Charge $charge) use (&$studentIds): void {
            $charge->update([
                'voided_at' => now(),
                'status' => self::VOID_STATUS,
                'notes' => trim($charge->notes."\nAnulado: ".self::VOID_REASON),
            ]);
            $studentIds[$charge->student_id] = true;
        });

        foreach (array_keys($studentIds) as $studentId) {
            AlertEngine::evaluateFinanceForStudent((int) $studentId);
        }

        $this->info('Cargos anulados: '.count($ids).'. Alumnos recalculados: '.count($studentIds).'.');

        return self::SUCCESS;
    }
}
