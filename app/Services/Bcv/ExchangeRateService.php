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
        return Cache::remember('bcv_api_latest_usd', now()->addMinutes(10), function () {
            try {
                $payload = $this->client->latest();
                $snapshot = $this->snapshotFromLatestPayload($payload);

                $this->persistSnapshot($snapshot);

                return $snapshot;
            } catch (\Throwable $exception) {
                Log::warning('BCV API latest failed, using local fallback', [
                    'message' => $exception->getMessage(),
                ]);

                return $this->fallbackSnapshot();
            }
        });
    }

    /**
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    public function getUsdRateForDate(?Carbon $date = null): array
    {
        if ($date === null || $date->isToday()) {
            return $this->getLatestUsdRate();
        }

        $local = ExchangeRate::query()
            ->where('currency', 'USD')
            ->whereDate('effective_at', $date->toDateString())
            ->orderByDesc('effective_at')
            ->first();

        if ($local) {
            return $this->snapshotFromModel($local);
        }

        try {
            $payload = $this->client->byDate($date->toDateString());
            $snapshot = $this->snapshotFromByDatePayload($payload);
            $this->persistSnapshot($snapshot);

            return $snapshot;
        } catch (\Throwable $exception) {
            Log::warning('BCV API by-date failed, using latest fallback', [
                'date' => $date->toDateString(),
                'message' => $exception->getMessage(),
            ]);

            return $this->getLatestUsdRate();
        }
    }

    public function syncLatest(): ExchangeRate
    {
        $payload = $this->client->latest();
        $snapshot = $this->snapshotFromLatestPayload($payload);

        return $this->persistSnapshot($snapshot);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    private function snapshotFromLatestPayload(array $payload): array
    {
        return [
            'rate' => (float) ($payload['rates']['USD'] ?? 0),
            'effective_at' => ! empty($payload['effective_at']) ? Carbon::parse($payload['effective_at']) : null,
            'captured_at' => ! empty($payload['captured_at']) ? Carbon::parse($payload['captured_at']) : now(),
            'source' => (string) ($payload['provider'] ?? 'bcv_api'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{rate: float, effective_at: ?Carbon, captured_at: ?Carbon, source: string}
     */
    private function snapshotFromByDatePayload(array $payload): array
    {
        $usd = $payload['rates']['USD'] ?? [];

        return [
            'rate' => (float) ($usd['rate'] ?? 0),
            'effective_at' => ! empty($usd['effective_at']) ? Carbon::parse($usd['effective_at']) : (! empty($payload['date']) ? Carbon::parse($payload['date']) : null),
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
    private function fallbackSnapshot(): array
    {
        $local = ExchangeRate::query()
            ->where('currency', 'USD')
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
    private function persistSnapshot(array $snapshot): ExchangeRate
    {
        if ($snapshot['rate'] <= 0) {
            throw new \RuntimeException('BCV rate payload did not include a valid USD rate.');
        }

        return ExchangeRate::query()->create([
            'currency' => 'USD',
            'rate' => $snapshot['rate'],
            'effective_at' => $snapshot['effective_at'] ?? now(),
            'captured_at' => $snapshot['captured_at'],
            'source' => $snapshot['source'],
        ]);
    }
}
