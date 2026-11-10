<?php

namespace Tests\Feature;

use App\Mail\DailyPendingMail;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Bcv\ExchangeRateService;
use App\Support\TodayInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExchangeRateSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00'); // martes
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rate(string $currency, string $date, float $rate = 300.0): ExchangeRate
    {
        return ExchangeRate::create(['currency' => $currency, 'rate' => $rate, 'effective_at' => $date, 'captured_at' => $date, 'source' => 'bcv']);
    }

    private function bcvItem(User $user): ?array
    {
        return collect(TodayInbox::for($user))->firstWhere('key', 'bcv_stale');
    }

    public function test_staleness_uses_business_days(): void
    {
        $this->assertTrue($this->rate('USD', '2026-11-06')->isStale()); // viernes, hoy martes
        $this->assertFalse($this->rate('USD', '2026-11-09')->isStale()); // lunes

        Carbon::setTestNow('2026-11-09 09:00:00'); // lunes: la del viernes sigue vigente
        $this->assertFalse(ExchangeRate::query()->where('effective_at', '2026-11-06 00:00:00')->first()->isStale());
    }

    public function test_page_shows_current_rates_history_and_stale_alert_reaches_today_and_daily_mail(): void
    {
        $this->rate('USD', '2026-11-05', 290);
        $this->rate('EUR', '2026-11-05', 330);

        $this->actingAs($this->master)->get(route('settings.exchange-rates.index'))
            ->assertOk()
            ->assertSee('Tasas BCV')
            ->assertSee('Desactualizada')
            ->assertSee('Bs 290,0000')
            ->assertSee('Cargar tasa a mano');

        $item = $this->bcvItem($this->master);
        $this->assertSame(2, $item['count']);
        $this->assertSame('danger', $item['tone']);
        $this->assertStringContainsString('05/11/2026', $item['hint']);

        Mail::fake();
        $this->artisan('admins:send-daily-pending')->assertSuccessful();
        Mail::assertQueued(DailyPendingMail::class, fn (DailyPendingMail $mail) => collect($mail->items)->contains('key', 'bcv_stale'));
    }

    public function test_sync_now_saves_rates_and_clears_alert(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'provider' => 'bcv', 'rates' => ['USD' => '310.5', 'EUR' => '350.1'], 'effective_at' => '2026-11-10T00:00:00-04:00', 'captured_at' => '2026-11-10T05:40:00-04:00'])]);
        $this->rate('USD', '2026-11-05');

        $this->actingAs($this->master)->post(route('settings.exchange-rates.sync'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'USD Bs 310,5000'));

        $this->assertSame(310.5, ExchangeRate::latestFor('USD')->rate);
        $this->assertSame(0, $this->bcvItem($this->master)['count']);
        $this->assertTrue(AuditLog::query()->where('action', 'exchange_rates.synced')->exists());
    }

    public function test_sync_failure_is_reported_not_silent(): void
    {
        Http::fake(['*' => Http::response(['message' => 'down'], 503)]);
        $this->rate('USD', '2026-11-05');
        $this->rate('EUR', '2026-11-05');

        $this->actingAs($this->master)->post(route('settings.exchange-rates.sync'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'El BCV no respondió'));
        $this->assertTrue(AuditLog::query()->where('action', 'exchange_rates.sync_failed')->exists());
        $this->assertSame(2, $this->bcvItem($this->master)['count']);
    }

    public function test_manual_rate_requires_reason_is_audited_and_used_as_fallback(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $this->rate('USD', '2026-11-05', 290);

        $this->actingAs($this->master)->post(route('settings.exchange-rates.manual'), ['currency' => 'USD', 'rate' => '312.25', 'effective_at' => '2026-11-10', 'note' => ''])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->master)->post(route('settings.exchange-rates.manual'), ['currency' => 'USD', 'rate' => '312.25', 'effective_at' => '2026-11-10', 'note' => 'El BCV no carga desde la mañana'])
            ->assertSessionHas('success');

        $manual = ExchangeRate::latestFor('USD');
        $this->assertSame(ExchangeRate::SOURCE_MANUAL, $manual->source);
        $this->assertSame($this->master->id, $manual->created_by);
        $this->assertSame(312.25, app(ExchangeRateService::class)->getLatestUsdRate()['rate']);
        $this->assertTrue(AuditLog::query()->where('action', 'exchange_rates.manual')->exists());
        $this->actingAs($this->master)->get(route('settings.exchange-rates.index'))->assertSee('El BCV no carga desde la mañana');
    }

    public function test_only_master_sets_manual_rate_and_non_admins_cannot_open_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $this->actingAs($admin)->get(route('settings.exchange-rates.index'))->assertOk()->assertDontSee('Cargar tasa a mano');
        $this->actingAs($admin)->post(route('settings.exchange-rates.manual'), ['currency' => 'USD', 'rate' => '1', 'effective_at' => '2026-11-10', 'note' => 'prueba de permisos'])->assertForbidden();

        $teacher = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]);
        $this->actingAs($teacher)->get(route('settings.exchange-rates.index'))->assertForbidden();
        $this->assertNull($this->bcvItem($teacher));
    }
}
