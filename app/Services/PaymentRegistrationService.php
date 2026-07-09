<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\Student;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\FinanceReconcile;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaymentRegistrationService
{
    public function __construct(
        private readonly PaymentReceiptNotifier $paymentReceiptNotifier,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?Request $request = null): Payment
    {
        $paymentMethod = PaymentMethod::query()
            ->whereKey($data['payment_method_id'])
            ->where('is_active', true)
            ->first();

        if (! $paymentMethod || $paymentMethod->currency !== $data['currency']) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'El método de pago no corresponde a la moneda seleccionada.',
            ]);
        }

        $data['method'] = $paymentMethod->label;

        $student = Student::findOrFail($data['student_id']);
        $data['campus_id'] = $student->campus_id;

        $selectedChargeIds = collect($data['charge_ids'] ?? [])
            ->push($data['charge_id'] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $charges = $this->resolveCharges($selectedChargeIds, $data);

        $converted = $charges->isNotEmpty()
            ? PaymentCurrencyConverter::resolveForCharge($data['currency'], (float) $data['original_amount'], $charges->first())
            : PaymentCurrencyConverter::resolve($data['currency'], (float) $data['original_amount']);
        $data['amount'] = $converted['amount'];

        if ($selectedChargeIds->isNotEmpty()) {
            $availableToApply = $charges->sum(fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge));
            if ((float) $data['amount'] > (float) $availableToApply) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto excede el saldo disponible de los cargos seleccionados.',
                ]);
            }
        }

        $payment = Payment::create(array_merge($data, [
            'currency' => $converted['currency'],
            'original_amount' => $converted['original_amount'],
            'exchange_rate' => $converted['exchange_rate'],
            'exchange_rate_effective_at' => $converted['exchange_rate_effective_at'],
            'payment_method_id' => $paymentMethod->id,
            'charge_id' => null,
            'paid_at_datetime' => now(),
            'status' => 'confirmed',
            'received_by' => $request?->user()?->id,
        ]));

        $this->applyAllocations($payment, $charges, $data, $selectedChargeIds);
        $this->createReceipt($payment, $data);

        if ($request) {
            AuditTrail::log($request, 'finance.payment.create', $payment, $data);
        }

        AlertEngine::evaluateFinanceForStudent((int) $data['student_id']);

        $payment->load(['receipt', 'student.representatives', 'allocations.charge', 'paymentMethod', 'charge']);
        $this->paymentReceiptNotifier->notify($payment);

        return $payment;
    }

    /**
     * @param  Collection<int, int>  $selectedChargeIds
     * @param  array<string, mixed>  $data
     */
    private function resolveCharges(Collection $selectedChargeIds, array $data): Collection
    {
        if ($selectedChargeIds->isEmpty()) {
            return collect();
        }

        $charges = Charge::query()
            ->with(['paymentAllocations', 'payments'])
            ->whereIn('id', $selectedChargeIds)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if ($charges->count() !== $selectedChargeIds->count()) {
            throw ValidationException::withMessages([
                'charge_ids' => 'Uno o más cargos seleccionados no existen.',
            ]);
        }

        foreach ($charges as $charge) {
            if ((int) $charge->campus_id !== (int) $data['campus_id']) {
                abort(403);
            }
            if ((int) $charge->student_id !== (int) $data['student_id']) {
                abort(403);
            }
        }

        return $charges;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, int>  $selectedChargeIds
     */
    private function applyAllocations(Payment $payment, Collection $charges, array $data, Collection $selectedChargeIds): void
    {
        if ($charges->isEmpty()) {
            return;
        }

        $remaining = (float) $payment->amount;
        foreach ($charges as $charge) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = FinanceReconcile::outstandingForCharge($charge);
            if ($outstanding <= 0) {
                continue;
            }

            $applied = min($remaining, $outstanding);
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'charge_id' => $charge->id,
                'amount_applied' => $applied,
            ]);

            $remaining -= $applied;
        }

        foreach ($charges as $charge) {
            FinanceReconcile::syncCharge($charge);
        }

        if (! empty($data['balance_due_date'])) {
            foreach ($charges as $charge) {
                if (FinanceReconcile::outstandingForCharge($charge) > 0) {
                    $charge->update(['due_date' => $data['balance_due_date']]);
                }
            }
        }

        if ($selectedChargeIds->count() === 1) {
            $payment->update(['charge_id' => $selectedChargeIds->first()]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createReceipt(Payment $payment, array $data): Receipt
    {
        return Receipt::create([
            'campus_id' => $data['campus_id'],
            'payment_id' => $payment->id,
            'receipt_number' => 'R-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
            'issued_at' => $data['paid_at'],
        ]);
    }
}
