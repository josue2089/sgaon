<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentSubmission
{
    /**
     * @return array<string, mixed>
     */
    public static function validatedCurrencyPayload(Request $request, ?Charge $charge = null): array
    {
        $data = $request->validate([
            'currency' => ['required', Rule::in([PaymentCurrencyConverter::CURRENCY_USD, PaymentCurrencyConverter::CURRENCY_VES])],
            'original_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentMethod = PaymentMethod::query()
            ->whereKey($data['payment_method_id'])
            ->where('is_active', true)
            ->first();

        if (! $paymentMethod || $paymentMethod->currency !== $data['currency']) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'El método de pago no corresponde a la moneda seleccionada.',
            ]);
        }

        $converted = PaymentCurrencyConverter::resolve(
            $data['currency'],
            (float) $data['original_amount'],
        );

        if ($charge) {
            $outstanding = FinanceReconcile::outstandingForCharge($charge);
            if ($converted['amount'] > $outstanding + 0.009) {
                throw ValidationException::withMessages([
                    'original_amount' => 'El monto excede el saldo pendiente del cargo.',
                ]);
            }
        }

        return array_merge($data, $converted, [
            'payment_method' => $paymentMethod->label,
            'payment_method_model' => $paymentMethod,
        ]);
    }
}
