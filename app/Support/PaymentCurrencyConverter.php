<?php

namespace App\Support;

use App\Models\Charge;
use App\Services\Bcv\ExchangeRateService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PaymentCurrencyConverter
{
    public const CURRENCY_USD = 'USD';

    public const CURRENCY_VES = 'VES';

    public const CURRENCY_EUR = 'EUR';

    /**
     * @return array{
     *     currency: string,
     *     original_amount: float,
     *     amount: float,
     *     exchange_rate: ?float,
     *     exchange_rate_effective_at: ?Carbon
     * }
     */
    public static function resolve(
        string $currency,
        float $originalAmount,
        ?float $exchangeRate = null,
        ?Carbon $exchangeRateEffectiveAt = null,
    ): array {
        $currency = strtoupper($currency);

        if (! in_array($currency, [self::CURRENCY_USD, self::CURRENCY_VES, self::CURRENCY_EUR], true)) {
            throw ValidationException::withMessages([
                'currency' => 'La moneda seleccionada no es válida.',
            ]);
        }

        if ($originalAmount <= 0) {
            throw ValidationException::withMessages([
                'original_amount' => 'El monto debe ser mayor a cero.',
            ]);
        }

        if ($currency === self::CURRENCY_USD) {
            $rounded = round($originalAmount, 2);

            return [
                'currency' => self::CURRENCY_USD,
                'original_amount' => $rounded,
                'amount' => $rounded,
                'exchange_rate' => null,
                'exchange_rate_effective_at' => null,
            ];
        }

        if ($currency === self::CURRENCY_EUR) {
            $rounded = round($originalAmount, 2);

            return [
                'currency' => self::CURRENCY_EUR,
                'original_amount' => $rounded,
                'amount' => $rounded,
                'exchange_rate' => null,
                'exchange_rate_effective_at' => null,
            ];
        }

        $snapshot = $exchangeRate !== null && $exchangeRate > 0
            ? [
                'rate' => $exchangeRate,
                'effective_at' => $exchangeRateEffectiveAt,
            ]
            : app(ExchangeRateService::class)->getLatestUsdRate();

        $rate = (float) ($snapshot['rate'] ?? 0);
        if ($rate <= 0) {
            throw ValidationException::withMessages([
                'currency' => 'No hay tasa BCV disponible para convertir el pago en bolívares.',
            ]);
        }

        return [
            'currency' => self::CURRENCY_VES,
            'original_amount' => round($originalAmount, 2),
            'amount' => round($originalAmount / $rate, 2),
            'exchange_rate' => $rate,
            'exchange_rate_effective_at' => $snapshot['effective_at'] ?? null,
        ];
    }

    /**
     * @return array{
     *     currency: string,
     *     original_amount: float,
     *     amount: float,
     *     exchange_rate: ?float,
     *     exchange_rate_effective_at: ?Carbon
     * }
     */
    public static function resolveForCharge(
        string $currency,
        float $originalAmount,
        Charge $charge,
        ?float $exchangeRate = null,
        ?Carbon $exchangeRateEffectiveAt = null,
    ): array {
        $currency = strtoupper($currency);
        $chargeCurrency = $charge->currencyCode();

        if ($chargeCurrency === self::CURRENCY_EUR) {
            if ($currency === self::CURRENCY_EUR) {
                $rounded = round($originalAmount, 2);

                return [
                    'currency' => self::CURRENCY_EUR,
                    'original_amount' => $rounded,
                    'amount' => $rounded,
                    'exchange_rate' => null,
                    'exchange_rate_effective_at' => null,
                ];
            }

            if ($currency === self::CURRENCY_VES) {
                $snapshot = $exchangeRate !== null && $exchangeRate > 0
                    ? [
                        'rate' => $exchangeRate,
                        'effective_at' => $exchangeRateEffectiveAt,
                    ]
                    : app(ExchangeRateService::class)->getLatestEurRate();

                $rate = (float) ($snapshot['rate'] ?? 0);
                if ($rate <= 0) {
                    throw ValidationException::withMessages([
                        'currency' => 'No hay tasa BCV EUR disponible para convertir el pago en bolívares.',
                    ]);
                }

                return [
                    'currency' => self::CURRENCY_VES,
                    'original_amount' => round($originalAmount, 2),
                    'amount' => round($originalAmount / $rate, 2),
                    'exchange_rate' => $rate,
                    'exchange_rate_effective_at' => $snapshot['effective_at'] ?? null,
                ];
            }

            throw ValidationException::withMessages([
                'currency' => 'Este cargo solo acepta pagos en EUR o Bs.',
            ]);
        }

        return self::resolve($currency, $originalAmount, $exchangeRate, $exchangeRateEffectiveAt);
    }

    public static function vesEquivalent(float $usdAmount, float $exchangeRate): float
    {
        if ($exchangeRate <= 0) {
            return 0.0;
        }

        return round($usdAmount * $exchangeRate, 2);
    }

    public static function eurVesEquivalent(float $eurAmount, float $eurExchangeRate): float
    {
        if ($eurExchangeRate <= 0) {
            return 0.0;
        }

        return round($eurAmount * $eurExchangeRate, 2);
    }

    public static function paymentMethodLabel(?\App\Models\PaymentMethod $paymentMethod, ?string $fallback = null): ?string
    {
        if ($paymentMethod) {
            return $paymentMethod->label;
        }

        return $fallback;
    }
}
