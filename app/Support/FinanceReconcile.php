<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Payment;

class FinanceReconcile
{
    /** Montos en centavos para comparar sin errores de coma flotante (33.33+33.33+33.34 = 100). #435 */
    public static function cents(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function paidTotalForCharge(Charge $charge): float
    {
        $directPaid = (float) Payment::query()
            ->where('charge_id', $charge->id)
            ->whereNull('voided_at')
            ->whereDoesntHave('allocations')
            ->sum('amount');

        $allocatedPaid = (float) $charge->paymentAllocations()
            ->whereHas('payment', fn ($query) => $query->whereNull('voided_at'))
            ->sum('amount_applied');

        return $directPaid + $allocatedPaid;
    }

    /**
     * Solo notifica por correo si el cargo cambió de estado en esta llamada: la conciliación diaria
     * corrige solicitudes atrasadas sin reenviar correos.
     */
    private static function syncMakeupRequest(Charge $charge, bool $statusChanged = false): void
    {
        if ($charge->charge_type === 'makeup' || $charge->makeup_request_id) {
            MakeupRecoveryEngine::syncWithCharge($charge, notify: $statusChanged);
        }
    }

    public static function outstandingForCharge(Charge $charge): float
    {
        if ($charge->voided_at) {
            return 0.0;
        }

        return max(0, (self::cents($charge->amount) - self::cents(self::paidTotalForCharge($charge))) / 100);
    }

    public static function syncCharge(Charge $charge): Charge
    {
        if ($charge->voided_at) {
            self::syncMakeupRequest($charge);

            return $charge;
        }

        $paidTotal = self::paidTotalForCharge($charge);
        $amount = (float) $charge->amount;

        if (self::cents($paidTotal) >= self::cents($amount) && self::cents($amount) > 0) {
            $nextStatus = 'paid';
        } elseif (self::cents($paidTotal) > 0) {
            $nextStatus = 'partial';
        } elseif ($charge->due_date && $charge->due_date->lt(today())) {
            // Vence al terminar el día de vencimiento (hora de Caracas), no al empezar. #368
            $nextStatus = 'overdue';
        } else {
            $nextStatus = 'pending';
        }

        $statusChanged = $charge->status !== $nextStatus;
        if ($statusChanged) {
            $charge->status = $nextStatus;
            $charge->save();
        }

        self::syncMakeupRequest($charge, $statusChanged);

        return $charge->refresh();
    }
}
