<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\User;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\FinanceReconcile;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChargeVoidService
{
    public const VOID_STATUS = 'void';

    /**
     * Anula un cargo sin borrarlo: deja de sumar saldo, alertas y recordatorios.
     * Solo se permite si no tiene pagos vigentes aplicados.
     */
    public function void(Charge $charge, User $user, string $reason, ?Request $request = null): Charge
    {
        if ($charge->voided_at) {
            throw ValidationException::withMessages([
                'reason' => 'Este cargo ya fue anulado.',
            ]);
        }

        if (FinanceReconcile::paidTotalForCharge($charge) > 0) {
            throw ValidationException::withMessages([
                'reason' => 'El cargo tiene pagos aplicados. Primero anule el pago y luego el cargo.',
            ]);
        }

        $charge->update([
            'voided_at' => now(),
            'voided_by' => $user->id,
            'void_reason' => $reason,
            'status' => self::VOID_STATUS,
        ]);

        if ($request) {
            AuditTrail::log($request, 'finance.charge.void', $charge, [
                'reason' => $reason,
                'concept' => $charge->concept,
                'amount' => $charge->amount,
                'currency' => $charge->currency,
            ]);
        }

        AlertEngine::evaluateFinanceForStudent((int) $charge->student_id);
        FinanceReconcile::syncCharge($charge->refresh());

        return $charge->refresh();
    }
}
