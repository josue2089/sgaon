<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Payment;

class FinanceReconcile
{
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

        return max(0, (float) $charge->amount - self::paidTotalForCharge($charge));
    }

    public static function syncCharge(Charge $charge): Charge
    {
        if ($charge->voided_at) {
            self::syncMakeupRequest($charge);

            return $charge;
        }

        $paidTotal = self::paidTotalForCharge($charge);
        $amount = (float) $charge->amount;

        if ($paidTotal >= $amount && $amount > 0) {
            $nextStatus = 'paid';
        } elseif ($paidTotal > 0) {
            $nextStatus = 'partial';
        } elseif ($charge->due_date && $charge->due_date->isPast()) {
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
