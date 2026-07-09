<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Payment;

class MoneyFormat
{
    public static function usd(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    public static function eur(float $amount): string
    {
        return '€'.number_format($amount, 2, ',', '.');
    }

    public static function ves(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }

    public static function chargeAmount(Charge $charge, ?float $eurVesRate = null): string
    {
        $amount = (float) $charge->amount;
        $currency = $charge->currencyCode();

        if ($currency === PaymentCurrencyConverter::CURRENCY_EUR) {
            $line = self::eur($amount);
            if ($eurVesRate && $eurVesRate > 0) {
                $line .= ' (~ '.self::ves(PaymentCurrencyConverter::eurVesEquivalent($amount, $eurVesRate)).')';
            }

            return $line;
        }

        return self::usd($amount);
    }

    public static function dualLine(Payment $payment): string
    {
        $currency = strtoupper((string) ($payment->currency ?: PaymentCurrencyConverter::CURRENCY_USD));
        $ledgerAmount = (float) $payment->amount;
        $originalAmount = (float) ($payment->original_amount ?? $payment->amount);

        if ($currency === PaymentCurrencyConverter::CURRENCY_VES) {
            $rate = $payment->exchange_rate ? number_format((float) $payment->exchange_rate, 4, ',', '.') : 'N/D';

            return self::ves($originalAmount).' (tasa '.$rate.') → '.self::formatLedgerAmount($ledgerAmount, $payment->charge?->currencyCode());
        }

        if ($currency === PaymentCurrencyConverter::CURRENCY_EUR) {
            return self::eur($originalAmount);
        }

        return self::usd($ledgerAmount);
    }

    public static function formatLedgerAmount(float $amount, ?string $chargeCurrency = null): string
    {
        $currency = strtoupper((string) ($chargeCurrency ?: PaymentCurrencyConverter::CURRENCY_USD));

        return match ($currency) {
            PaymentCurrencyConverter::CURRENCY_EUR => self::eur($amount),
            default => self::usd($amount),
        };
    }
}
