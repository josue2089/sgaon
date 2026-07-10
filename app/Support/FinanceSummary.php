<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinanceSummary
{
    /**
     * @return array{
     *     total_invoiced: float,
     *     total_collected: float,
     *     total_outstanding: float,
     *     currency: string,
     *     projection: Collection<int, array{label: string, due_date: string, amount: float}>
     * }
     */
    public static function build(
        ?User $user,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        string $currency = PaymentCurrencyConverter::CURRENCY_EUR,
        ?int $campusId = null,
    ): array {
        $charges = self::chargesQuery($user, $startDate, $endDate, $currency, $campusId)->get();
        $payments = self::paymentsQuery($user, $startDate, $endDate, $currency, $campusId)->get();

        $totalInvoiced = (float) $charges->sum('amount');
        $totalCollected = round((float) $payments->sum(
            fn (Payment $payment) => self::paymentAmountForCurrency($payment, $currency)
        ), 2);

        $openCharges = self::chargesQuery($user, null, null, $currency, $campusId)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->get();

        $totalOutstanding = round((float) $openCharges->sum(
            fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge)
        ), 2);

        $projection = $openCharges
            ->filter(fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge) > 0 && $charge->due_date)
            ->when($startDate, fn (Collection $group) => $group->filter(
                fn (Charge $charge) => $charge->due_date->greaterThanOrEqualTo($startDate->copy()->startOfDay())
            ))
            ->when($endDate, fn (Collection $group) => $group->filter(
                fn (Charge $charge) => $charge->due_date->lessThanOrEqualTo($endDate->copy()->endOfDay())
            ))
            ->groupBy(fn (Charge $charge) => $charge->due_date->format('Y-m'))
            ->map(function (Collection $group, string $monthKey) {
                $dueDate = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();

                return [
                    'label' => $dueDate->translatedFormat('F Y'),
                    'due_date' => $dueDate->toDateString(),
                    'amount' => round((float) $group->sum(fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge)), 2),
                ];
            })
            ->sortBy('due_date')
            ->values();

        return [
            'total_invoiced' => round($totalInvoiced, 2),
            'total_collected' => $totalCollected,
            'total_outstanding' => $totalOutstanding,
            'currency' => $currency,
            'projection' => $projection,
        ];
    }

    /**
     * @return Builder<Charge>
     */
    public static function chargesQuery(
        ?User $user,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        string $currency = PaymentCurrencyConverter::CURRENCY_EUR,
        ?int $campusId = null,
    ): Builder {
        $query = CampusScope::apply(
            Charge::query()
                ->whereNull('voided_at')
                ->where('currency', $currency),
            $user
        );

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        if ($startDate) {
            $query->where('created_at', '>=', $startDate->copy()->startOfDay());
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate->copy()->endOfDay());
        }

        return $query;
    }

    /**
     * @return Builder<Payment>
     */
    public static function paymentsQuery(
        ?User $user,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        string $currency = PaymentCurrencyConverter::CURRENCY_EUR,
        ?int $campusId = null,
    ): Builder {
        $query = CampusScope::apply(
            Payment::query()->whereNull('voided_at'),
            $user
        );

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        if ($currency === PaymentCurrencyConverter::CURRENCY_EUR) {
            $query->where('currency', PaymentCurrencyConverter::CURRENCY_EUR);
        } else {
            $query->whereIn('currency', [
                PaymentCurrencyConverter::CURRENCY_USD,
                PaymentCurrencyConverter::CURRENCY_VES,
            ]);
        }

        if ($startDate) {
            $query->where('paid_at', '>=', $startDate->copy()->startOfDay());
        }

        if ($endDate) {
            $query->where('paid_at', '<=', $endDate->copy()->endOfDay());
        }

        return $query;
    }

    public static function paymentAmountForCurrency(Payment $payment, string $currency): float
    {
        $paymentCurrency = strtoupper((string) ($payment->currency ?: PaymentCurrencyConverter::CURRENCY_USD));

        if ($currency === PaymentCurrencyConverter::CURRENCY_EUR) {
            return $paymentCurrency === PaymentCurrencyConverter::CURRENCY_EUR
                ? (float) ($payment->original_amount ?? $payment->amount)
                : 0.0;
        }

        if ($paymentCurrency === PaymentCurrencyConverter::CURRENCY_VES) {
            return (float) $payment->amount;
        }

        return (float) ($payment->original_amount ?? $payment->amount);
    }
}
