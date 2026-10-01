<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\User;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\FinanceReconcile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentVoidService
{
    public const VOID_STATUS = 'void';

    /**
     * Anula un pago sin borrarlo: deja de contar como pagado y los cargos a los que se aplicó
     * vuelven al estado que corresponde según los pagos restantes.
     */
    public function void(Payment $payment, User $user, string $reason, ?Request $request = null): Payment
    {
        if ($payment->voided_at) {
            throw ValidationException::withMessages([
                'reason' => 'Este pago ya fue anulado.',
            ]);
        }

        DB::transaction(function () use ($payment, $user, $reason): void {
            $payment->update([
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => $reason,
                'status' => self::VOID_STATUS,
            ]);

            $chargeIds = $payment->allocations()->pluck('charge_id')
                ->push($payment->charge_id)
                ->filter()
                ->unique();

            Charge::query()->whereIn('id', $chargeIds)->each(
                fn (Charge $charge) => FinanceReconcile::syncCharge($charge)
            );
        });

        if ($request) {
            AuditTrail::log($request, 'finance.payment.void', $payment, [
                'reason' => $reason,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->reference,
            ]);
        }

        AlertEngine::evaluateFinanceForStudent((int) $payment->student_id);

        return $payment->refresh();
    }
}
