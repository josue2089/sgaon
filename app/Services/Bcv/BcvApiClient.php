<?php

namespace App\Services\Bcv;

use Illuminate\Support\Facades\Http;

class BcvApiClient
{
    private function client()
    {
        return Http::timeout((int) config('services.bcv_api.timeout'))
            ->acceptJson()
            ->withHeaders([
                'X-API-KEY' => (string) config('services.bcv_api.api_key'),
            ]);
    }

    public function latest(): array
    {
        return $this->client()
            ->get($this->endpoint('/latest'))
            ->throw()
            ->json();
    }

    public function byDate(string $date): array
    {
        return $this->client()
            ->get($this->endpoint('/by-date'), ['date' => $date])
            ->throw()
            ->json();
    }

    public function history(array $params = []): array
    {
        return $this->client()
            ->get($this->endpoint('/history'), $params)
            ->throw()
            ->json();
    }

    public function series(array $params): array
    {
        return $this->client()
            ->get($this->endpoint('/series'), $params)
            ->throw()
            ->json();
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.bcv_api.base_url'), '/').$path;
    }
}
