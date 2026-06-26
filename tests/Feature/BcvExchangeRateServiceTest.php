<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Bcv\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BcvExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_rate_is_cached_and_persisted(): void
    {
        config([
            'services.bcv_api.base_url' => 'https://aerious.uk/api/exchange-rates/bcv',
            'services.bcv_api.api_key' => 'test-key',
        ]);

        Http::fake([
            'aerious.uk/api/exchange-rates/bcv/latest' => Http::response([
                'success' => true,
                'provider' => 'bcv',
                'rates' => ['USD' => '476.43420000'],
                'effective_at' => '2026-04-10T00:00:00-04:00',
                'captured_at' => '2026-04-10T09:00:00-04:00',
            ]),
        ]);

        Cache::flush();

        $service = app(ExchangeRateService::class);
        $first = $service->getLatestUsdRate();
        $second = $service->getLatestUsdRate();

        $this->assertEquals(476.4342, $first['rate']);
        $this->assertEquals($first['rate'], $second['rate']);
        $this->assertDatabaseCount('exchange_rates', 1);
        Http::assertSentCount(1);
    }

    public function test_fallback_uses_local_rate_when_api_fails(): void
    {
        config([
            'services.bcv_api.base_url' => 'https://aerious.uk/api/exchange-rates/bcv',
            'services.bcv_api.api_key' => 'test-key',
        ]);

        ExchangeRate::create([
            'currency' => 'USD',
            'rate' => 88.5,
            'effective_at' => now()->subDay(),
            'captured_at' => now()->subDay(),
            'source' => 'local',
        ]);

        Http::fake([
            'aerious.uk/api/exchange-rates/bcv/latest' => Http::response([], 500),
        ]);

        Cache::flush();

        $snapshot = app(ExchangeRateService::class)->getLatestUsdRate();

        $this->assertEquals(88.5, $snapshot['rate']);
        $this->assertSame('local', $snapshot['source']);
    }

    public function test_exchange_rate_endpoint_requires_auth(): void
    {
        ExchangeRate::create([
            'currency' => 'USD',
            'rate' => 90,
            'effective_at' => now(),
            'captured_at' => now(),
            'source' => 'test',
        ]);

        $this->get(route('finance.exchange-rate'))->assertRedirect(route('login'));

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)
            ->getJson(route('finance.exchange-rate'))
            ->assertOk()
            ->assertJsonPath('rate', 90);
    }
}
