<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\ChargePaymentRequest;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRegistrationService
{
    public function __construct(
        private readonly PaymentReceiptNotifier $paymentReceiptNotifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?Request $request = null): Payment
    {
        // Todo o nada: pago, asignaciones, recibo y estado de los cargos. El correo sale después del commit. #435
        $payment = DB::transaction(fn () => $this->registerInTransaction($data, $request));

        $this->afterPayment($payment);

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function registerInTransaction(array $data, ?Request $request): Payment
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
        $this->guardDuplicateReference($data, $paymentMethod->id);

        $converted = $charges->isNotEmpty()
            ? PaymentCurrencyConverter::resolveForCharge($data['currency'], (float) $data['original_amount'], $charges->first())
            : PaymentCurrencyConverter::resolve($data['currency'], (float) $data['original_amount']);
        $data['amount'] = $converted['amount'];

        if ($selectedChargeIds->isNotEmpty()) {
            $availableToApply = $charges->sum(fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge));
            if (FinanceReconcile::cents($data['amount']) > FinanceReconcile::cents($availableToApply)) {
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

        return $payment;
    }

    /**
     * Aprueba un comprobante del portal: crea el pago con la fecha de la transferencia y lo aplica al cargo.
     * Si el monto supera el saldo, el excedente va a otros cargos abiertos del alumno en la misma moneda y,
     * si aún sobra, queda como pago sin aplicar (nunca se descarta). Bloquea la solicitud para que dos
     * aprobaciones simultáneas no creen dos pagos. #435
     *
     * @return array{payment: Payment, unapplied: float, extra_charges: Collection<int, Charge>}
     */
    public function approveRequest(ChargePaymentRequest $paymentRequest, string $paidAt, Request $request): array
    {
        $result = DB::transaction(function () use ($paymentRequest, $paidAt, $request) {
            $locked = ChargePaymentRequest::query()->lockForUpdate()->findOrFail($paymentRequest->id);
            if ($locked->status !== ChargePaymentRequest::STATUS_PENDING_VALIDATION) {
                throw ValidationException::withMessages(['action' => 'La solicitud ya fue procesada.']);
            }

            $charge = Charge::query()->lockForUpdate()->findOrFail($locked->charge_id);
            $otherCharges = Charge::query()
                ->lockForUpdate()
                ->where('student_id', $locked->student_id)
                ->whereKeyNot($charge->id)
                ->whereNull('voided_at')
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->where(fn ($query) => $charge->currencyCode() === 'USD'
                    ? $query->where('currency', 'USD')->orWhereNull('currency')
                    : $query->where('currency', $charge->currencyCode()))
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();
            $targets = collect([$charge])->merge($otherCharges)
                ->filter(fn (Charge $target) => FinanceReconcile::cents(FinanceReconcile::outstandingForCharge($target)) > 0)
                ->values();

            if ($targets->isEmpty() || $targets->first()->isNot($charge)) {
                throw ValidationException::withMessages(['action' => 'El cargo seleccionado ya no tiene saldo pendiente.']);
            }

            $amount = round((float) $locked->amount, 2);
            $payment = Payment::create([
                'campus_id' => $locked->campus_id,
                'student_id' => $locked->student_id,
                // Se liga al cargo después de crear las asignaciones: con charge_id ya puesto, el saldo
                // lo contaría como pago directo y la asignación quedaría en 0.
                'charge_id' => null,
                'amount' => $amount,
                'currency' => $locked->currency,
                'original_amount' => $locked->original_amount ?? $amount,
                'exchange_rate' => $locked->exchange_rate,
                'exchange_rate_effective_at' => $locked->exchange_rate_effective_at,
                'payment_method_id' => $locked->payment_method_id,
                'paid_at' => $paidAt,
                'paid_at_datetime' => now(),
                'method' => $locked->payment_method ?: 'Comprobante portal',
                'reference' => $locked->reference,
                'status' => 'confirmed',
                'received_by' => $request->user()?->id,
                'notes' => $locked->notes,
            ]);

            $remainingCents = FinanceReconcile::cents($amount);
            $applied = collect();
            foreach ($targets as $target) {
                if ($remainingCents <= 0) {
                    break;
                }
                $appliedCents = min($remainingCents, FinanceReconcile::cents(FinanceReconcile::outstandingForCharge($target)));
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'charge_id' => $target->id,
                    'amount_applied' => $appliedCents / 100,
                ]);
                $remainingCents -= $appliedCents;
                $applied->push($target);
            }
            $payment->update(['charge_id' => $charge->id]);
            foreach ($applied as $target) {
                FinanceReconcile::syncCharge($target);
            }

            $this->createReceipt($payment, ['campus_id' => $locked->campus_id, 'paid_at' => $paidAt]);

            $locked->forceFill([
                'status' => ChargePaymentRequest::STATUS_APPROVED,
                'validated_by' => $request->user()?->id,
                'validated_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $unapplied = $remainingCents / 100;
            AuditTrail::log($request, 'finance.payment_request.approve', $locked, [
                'payment_id' => $payment->id,
                'paid_at' => $paidAt,
                'applied_to' => $applied->pluck('id')->all(),
                'unapplied' => $unapplied,
            ]);

            return ['payment' => $payment, 'unapplied' => $unapplied, 'extra_charges' => $applied->slice(1)->values()];
        });

        $this->afterPayment($result['payment']);

        return $result;
    }

    /** Si el alumno o sus representantes tienen correo, se les envía el recibo. */
    public function receiptWillBeEmailed(Payment $payment): bool
    {
        return $this->paymentReceiptNotifier->hasRecipients($payment);
    }

    private function afterPayment(Payment $payment): void
    {
        AlertEngine::evaluateFinanceForStudent((int) $payment->student_id);

        $payment->load(['receipt', 'student.representatives', 'allocations.charge', 'paymentMethod', 'charge']);
        // Un fallo del correo no debe deshacer ni ocultar un pago ya guardado.
        try {
            $this->paymentReceiptNotifier->notify($payment);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Misma referencia y método en un pago vigente: casi siempre es un comprobante cargado dos veces.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardDuplicateReference(array $data, int $paymentMethodId): void
    {
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '' || ! empty($data['allow_duplicate_reference'])) {
            return;
        }

        $existing = self::findDuplicateReference($reference, $paymentMethodId);
        if ($existing) {
            throw ValidationException::withMessages([
                'reference' => 'Ya existe un pago con la referencia '.$reference.' ('.($existing->receipt?->receipt_number ?? 'pago #'.$existing->id).', '.$existing->paid_at?->format('d/m/Y').'). Si es correcto, marca "Registrar de todos modos".',
            ]);
        }
    }

    public static function findDuplicateReference(?string $reference, ?int $paymentMethodId, ?int $exceptPaymentId = null): ?Payment
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return null;
        }

        return Payment::query()
            ->with('receipt')
            ->whereNull('voided_at')
            ->whereRaw('LOWER(TRIM(reference)) = ?', [mb_strtolower($reference)])
            ->when($paymentMethodId, fn ($query) => $query->where('payment_method_id', $paymentMethodId))
            ->when($exceptPaymentId, fn ($query) => $query->whereKeyNot($exceptPaymentId))
            ->latest('id')
            ->first();
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
            ->lockForUpdate()
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

        $remaining = FinanceReconcile::cents($payment->amount);
        foreach ($charges as $charge) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = FinanceReconcile::cents(FinanceReconcile::outstandingForCharge($charge));
            if ($outstanding <= 0) {
                continue;
            }

            $applied = min($remaining, $outstanding);
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'charge_id' => $charge->id,
                'amount_applied' => $applied / 100,
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
