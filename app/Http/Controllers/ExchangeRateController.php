<?php

namespace App\Http\Controllers;

use App\Services\Bcv\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function latest(Request $request, ExchangeRateService $exchangeRateService): JsonResponse
    {
        abort_unless($request->user(), 401);

        $currency = strtoupper((string) $request->query('currency', 'USD'));
        $snapshot = match ($currency) {
            'EUR' => $exchangeRateService->getLatestEurRate(),
            default => $exchangeRateService->getLatestUsdRate(),
        };

        return response()->json([
            'currency' => $currency,
            'rate' => $snapshot['rate'],
            'effective_at' => $snapshot['effective_at']?->toIso8601String(),
            'captured_at' => $snapshot['captured_at']?->toIso8601String(),
            'source' => $snapshot['source'],
        ]);
    }
}
