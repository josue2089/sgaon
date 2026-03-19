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
            ->whereDoesntHave('allocations')
            ->sum('amount');

        $allocatedPaid = (float) $charge->paymentAllocations()->sum('amount_applied');

        return $directPaid + $allocatedPaid;
    }

    public static function outstandingForCharge(Charge $charge): float
    {
        return max(0, (float) $charge->amount - self::paidTotalForCharge($charge));
    }

    public static function syncCharge(Charge $charge): Charge
    {
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

        if ($charge->status !== $nextStatus) {
            $charge->status = $nextStatus;
            $charge->save();
        }

        return $charge->refresh();
    }
}
