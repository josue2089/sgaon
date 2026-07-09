<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\PaymentAllocation;
use App\Models\User;
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
    public static function build(?User $user, ?Carbon $startDate = null, ?Carbon $endDate = null, string $currency = PaymentCurrencyConverter::CURRENCY_EUR): array
    {
        $chargesQuery = CampusScope::apply(
            Charge::query()->whereNull('voided_at')->where('currency', $currency),
            $user
        );

        if ($startDate) {
            $chargesQuery->where('created_at', '>=', $startDate->copy()->startOfDay());
        }
        if ($endDate) {
            $chargesQuery->where('created_at', '<=', $endDate->copy()->endOfDay());
        }

        $charges = $chargesQuery->get();
        $totalInvoiced = (float) $charges->sum('amount');
        $totalOutstanding = (float) $charges->sum(
            fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge)
        );
        $totalCollected = round($totalInvoiced - $totalOutstanding, 2);

        $chargeIds = $charges->pluck('id');
        $allocatedCollected = (float) PaymentAllocation::query()
            ->whereIn('charge_id', $chargeIds)
            ->sum('amount_applied');

        if ($chargeIds->isNotEmpty()) {
            $totalCollected = $allocatedCollected;
            $totalOutstanding = round(max(0, $totalInvoiced - $totalCollected), 2);
        }

        $projection = $charges
            ->filter(fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge) > 0 && $charge->due_date)
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
            'total_collected' => round($totalCollected, 2),
            'total_outstanding' => round($totalOutstanding, 2),
            'currency' => $currency,
            'projection' => $projection,
        ];
    }
}
