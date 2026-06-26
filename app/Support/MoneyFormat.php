<?php

namespace App\Support;

use App\Models\Payment;

class MoneyFormat
{
    public static function usd(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    public static function ves(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }

    public static function dualLine(Payment $payment): string
    {
        $currency = strtoupper((string) ($payment->currency ?: PaymentCurrencyConverter::CURRENCY_USD));
        $usdAmount = (float) $payment->amount;
        $originalAmount = (float) ($payment->original_amount ?? $payment->amount);

        if ($currency === PaymentCurrencyConverter::CURRENCY_VES) {
            $rate = $payment->exchange_rate ? number_format((float) $payment->exchange_rate, 4, ',', '.') : 'N/D';

            return self::ves($originalAmount).' (tasa '.$rate.') → '.self::usd($usdAmount);
        }

        return self::usd($usdAmount);
    }
}
