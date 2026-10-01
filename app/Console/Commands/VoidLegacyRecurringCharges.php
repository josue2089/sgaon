<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\Payment;
use App\Support\AlertEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class VoidLegacyRecurringCharges extends Command
{
    public const VOID_STATUS = 'void';

    public const VOID_REASON = 'Cargo legacy 75$ no corresponde';

    public const PAYMENT_VOID_REASON = 'Pago "NO APLICA" registrado para saldar cargo legacy 75$';

    protected $signature = 'finance:void-legacy-recurring-charges
        {--dry-run : Solo lista los cargos y pagos, sin modificarlos}
        {--fake-payments : Anula también los pagos con referencia "NO APLICA" aplicados solo a cargos legacy}';

    protected $description = 'Anula los cargos de 75$ creados por el comando legacy finance:generate-recurring-charges.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';

        $charges = Charge::query()
            ->with(['student', 'payments', 'paymentAllocations.payment'])
            ->whereNull('voided_at')
            ->whereNull('enrollment_id')
            ->where('notes', 'like', 'Auto-generado por inscripción activa #%')
            ->where('concept', 'like', 'Mensualidad académica %')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $legacyIds = $charges->pluck('id')->flip();

        $fakePayments = collect();
        if ($this->option('fake-payments')) {
            [$fakePayments, $skippedPayments] = $this->fakePayments($legacyIds);

            $this->info($prefix.'Pagos "NO APLICA" a anular: '.$fakePayments->count().' (monto '.round($fakePayments->sum('amount'), 2).')');
            if ($fakePayments->isNotEmpty()) {
                $this->table(['Pago ID', 'Alumno ID', 'Monto', 'Moneda', 'Referencia', 'Fecha'], $fakePayments->map(fn (Payment $p) => [
                    $p->id, $p->student_id, $p->amount, $p->currency, $p->reference, $p->paid_at,
                ])->all());
            }

            $this->warn('Pagos "NO APLICA" que también tocan cargos reales (no se anulan): '.$skippedPayments->count());
            if ($skippedPayments->isNotEmpty()) {
                $this->table(['Pago ID', 'Alumno ID', 'Monto', 'Referencia'], $skippedPayments->map(fn (Payment $p) => [
                    $p->id, $p->student_id, $p->amount, $p->reference,
                ])->all());
            }
        }

        $excludedPaymentIds = $fakePayments->pluck('id')->flip();
        $toVoid = [];
        $withPayments = [];

        foreach ($charges as $charge) {
            $paid = $this->paidTotal($charge, $excludedPaymentIds);
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

        $this->info($prefix.'Cargos legacy sin pagos a anular: '.count($toVoid));
        if ($toVoid !== []) {
            $this->table($headers, $toVoid);
        }

        $this->warn('Cargos legacy con pagos (revisar manualmente, no se anulan): '.count($withPayments));
        if ($withPayments !== []) {
            $this->table($headers, $withPayments);
        }

        if ($dryRun || ($toVoid === [] && $fakePayments->isEmpty())) {
            return self::SUCCESS;
        }

        $studentIds = [];

        foreach ($fakePayments as $payment) {
            $payment->update([
                'voided_at' => now(),
                'status' => self::VOID_STATUS,
                'notes' => trim(($payment->notes ?? '')."\nAnulado: ".self::PAYMENT_VOID_REASON),
            ]);
            $studentIds[$payment->student_id] = true;
        }

        $ids = array_column($toVoid, 0);

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

        $this->info('Pagos anulados: '.$fakePayments->count().'. Cargos anulados: '.count($ids).'. Alumnos recalculados: '.count($studentIds).'.');

        return self::SUCCESS;
    }

    /**
     * Pagos "NO APLICA" vigentes, separados entre los que solo tocan cargos legacy y los que tocan otros cargos.
     *
     * @return array{0: Collection<int, Payment>, 1: Collection<int, Payment>}
     */
    private function fakePayments(Collection $legacyIds): array
    {
        $payments = Payment::query()
            ->with('allocations')
            ->whereNull('voided_at')
            ->where(fn ($query) => $query
                ->where('reference', 'like', '%NO APLICA%')
                ->orWhere('notes', 'like', '%NO APLICA%'))
            ->orderBy('id')
            ->get();

        $onlyLegacy = collect();
        $skipped = collect();

        foreach ($payments as $payment) {
            $chargeIds = $payment->allocations->isNotEmpty()
                ? $payment->allocations->pluck('charge_id')
                : collect([$payment->charge_id])->filter();

            if ($chargeIds->isEmpty()) {
                continue;
            }

            if ($chargeIds->every(fn ($id) => $legacyIds->has($id))) {
                $onlyLegacy->push($payment);
            } elseif ($chargeIds->contains(fn ($id) => $legacyIds->has($id))) {
                $skipped->push($payment);
            }
        }

        return [$onlyLegacy, $skipped];
    }

    private function paidTotal(Charge $charge, Collection $excludedPaymentIds): float
    {
        $direct = $charge->payments
            ->filter(fn (Payment $p) => $p->voided_at === null && ! $excludedPaymentIds->has($p->id))
            ->filter(fn (Payment $p) => ! $p->allocations()->exists())
            ->sum('amount');

        $allocated = $charge->paymentAllocations
            ->filter(fn ($a) => $a->payment && $a->payment->voided_at === null && ! $excludedPaymentIds->has($a->payment_id))
            ->sum('amount_applied');

        return round((float) $direct + (float) $allocated, 2);
    }
}
