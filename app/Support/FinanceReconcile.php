<?php

namespace App\Support;

use App\Models\Charge;

class FinanceReconcile
{
    public static function syncCharge(Charge $charge): Charge
    {
        $paidTotal = (float) $charge->payments()->sum('amount');
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
