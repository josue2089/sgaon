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

        $snapshot = $exchangeRateService->getLatestUsdRate();

        return response()->json([
            'currency' => 'USD',
            'rate' => $snapshot['rate'],
            'effective_at' => $snapshot['effective_at']?->toIso8601String(),
            'captured_at' => $snapshot['captured_at']?->toIso8601String(),
            'source' => $snapshot['source'],
        ]);
    }
}
