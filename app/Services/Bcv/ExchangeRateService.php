<?php

namespace App\Services\Bcv;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    public function __construct(private BcvApiClient $client)
    {
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    public function getLatestUsdRate(): array
    {
        return $this->rememberLatest('USD', fn () => $this->fetchAndPersistLatest('USD'));
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    public function getLatestEurRate(): array
    {
        return $this->rememberLatest('EUR', fn () => $this->fetchAndPersistLatest('EUR'));
    }

    /**
     * @return array{USD: array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}, EUR: array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}}
     */
    public function getLatestRates(): array
    {
        return [
            'USD' => $this->getLatestUsdRate(),
            'EUR' => $this->getLatestEurRate(),
        ];
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    public function getUsdRateForDate(?Carbon $date = null): array
    {
        return $this->getRateForDate('USD', $date);
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    public function getEurRateForDate(?Carbon $date = null): array
    {
        return $this->getRateForDate('EUR', $date);
    }

    public function syncLatest(): ExchangeRate
    {
        return $this->syncAllLatest()['USD'];
    }

    /**
     * @return array{USD: ExchangeRate, EUR: ExchangeRate}
     */
    public function syncAllLatest(): array
    {
        $payload = $this->client->latest();

        return [
            'USD' => $this->persistSnapshot($this->snapshotFromLatestPayload($payload, 'USD'), 'USD'),
            'EUR' => $this->persistSnapshot($this->snapshotFromLatestPayload($payload, 'EUR'), 'EUR'),
        ];
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    private function getRateForDate(string $currency, ?Carbon $date = null): array
    {
        if ($date === null || $date->isToday()) {
            return $currency === 'EUR' ? $this->getLatestEurRate() : $this->getLatestUsdRate();
        }

        $local = ExchangeRate::query()
            ->where('currency', $currency)
            ->whereDate('effective_at', $date->toDateString())
            ->orderByDesc('effective_at')
            ->first();

        if ($local) {
            return $this->snapshotFromModel($local);
        }

        try {
            $payload = $this->client->byDate($date->toDateString());
            $snapshot = $this->snapshotFromByDatePayload($payload, $currency);
            $this->persistSnapshot($snapshot, $currency);

            return $snapshot;
        } catch (\Throwable $exception) {
            Log::warning('BCV API by-date failed, using latest fallback', [
                'currency' => $currency,
                'date' => $date->toDateString(),
                'message' => $exception->getMessage(),
            ]);

            return $currency === 'EUR' ? $this->getLatestEurRate() : $this->getLatestUsdRate();
        }
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    private function rememberLatest(string $currency, callable $resolver): array
    {
        return Cache::remember('bcv_api_latest_'.strtolower($currency), now()->addMinutes(10), function () use ($currency, $resolver) {
            try {
                return $resolver();
            } catch (\Throwable $exception) {
                Log::warning('BCV API latest failed, using local fallback', [
                    'currency' => $currency,
                    'message' => $exception->getMessage(),
                ]);

                return $this->fallbackSnapshot($currency);
            }
        });
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    private function fetchAndPersistLatest(string $currency): array
    {
        $payload = $this->client->latest();
        $snapshot = $this->snapshotFromLatestPayload($payload, $currency);
        $this->persistSnapshot($snapshot, $currency);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    private function snapshotFromLatestPayload(array $payload, string $currency): array
    {
        return [
            'rate' => (float) ($payload['rates'][$currency] ?? 0),
            'effective_at' => ! empty($payload['effective_at']) ? Carbon::parse($payload['effective_at']) : null,
            'captured_at' => ! empty($payload['captured_at']) ? Carbon::parse($payload['captured_at']) : now(),
            'source' => (string) ($payload['provider'] ?? 'bcv_api'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    private function snapshotFromByDatePayload(array $payload, string $currency): array
    {
        $entry = $payload['rates'][$currency] ?? [];

        return [
            'rate' => (float) (is_array($entry) ? ($entry['rate'] ?? 0) : $entry),
            'effective_at' => ! empty($entry['effective_at'])
                ? Carbon::parse($entry['effective_at'])
                : (! empty($payload['date']) ? Carbon::parse($payload['date']) : null),
            'captured_at' => now(),
            'source' => (string) ($payload['provider'] ?? 'bcv_api'),
        ];
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    private function snapshotFromModel(ExchangeRate $rate): array
    {
        return [
            'rate' => (float) $rate->rate,
            'effective_at' => $rate->effective_at,
            'captured_at' => $rate->captured_at,
            'source' => (string) $rate->source,
        ];
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    private function fallbackSnapshot(string $currency): array
    {
        $local = ExchangeRate::query()
            ->where('currency', $currency)
            ->orderByDesc('effective_at')
            ->first();

        if ($local) {
            return $this->snapshotFromModel($local);
        }

        return [
            'rate' => 0.0,
            'effective_at' => null,
            'captured_at' => null,
            'source' => 'unavailable',
        ];
    }

    /**
     * @param  array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}  $snapshot
     */
    private function persistSnapshot(array $snapshot, string $currency): ExchangeRate
    {
        if ($snapshot['rate'] <= 0) {
            throw new \RuntimeException('BCV rate payload did not include a valid '.$currency.' rate.');
        }

        return ExchangeRate::query()->create([
            'currency' => $currency,
            'rate' => $snapshot['rate'],
            'effective_at' => $snapshot['effective_at'] ?? now(),
            'captured_at' => $snapshot['captured_at'],
            'source' => $snapshot['source'],
        ]);
    }
}
