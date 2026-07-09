<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_manage_payment_methods(): void
    {
        $master = User::factory()->create(['role' => 'admin', 'is_master' => true]);

        $create = $this->actingAs($master)->post(route('settings.payment-methods.store'), [
            'currency' => PaymentMethod::CURRENCY_USD,
            'method_type' => PaymentMethod::TYPE_ZELLE,
            'label' => 'Zelle Principal',
            'email' => 'zelle@onenglish.test',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $create->assertRedirect(route('settings.payment-methods.index'));
        $method = PaymentMethod::query()->first();
        $this->assertNotNull($method);
        $this->assertSame('Zelle Principal', $method->label);

        $update = $this->actingAs($master)->put(route('settings.payment-methods.update', $method), [
            'currency' => PaymentMethod::CURRENCY_VES,
            'method_type' => PaymentMethod::TYPE_TRANSFERENCIA,
            'label' => 'Transferencia Bs',
            'bank_name' => 'BNC',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $update->assertRedirect(route('settings.payment-methods.index'));
        $this->assertSame('Transferencia Bs', $method->fresh()->label);
        $this->assertSame(PaymentMethod::CURRENCY_VES, $method->fresh()->currency);

        $this->actingAs($master)
            ->delete(route('settings.payment-methods.destroy', $method))
            ->assertRedirect(route('settings.payment-methods.index'));

        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }

    public function test_master_admin_can_create_eur_payment_method(): void
    {
        $master = User::factory()->create(['role' => 'admin', 'is_master' => true]);

        $this->actingAs($master)->post(route('settings.payment-methods.store'), [
            'currency' => PaymentMethod::CURRENCY_EUR,
            'method_type' => PaymentMethod::TYPE_TRANSFERENCIA,
            'label' => 'Transferencia EUR',
            'email' => 'eur@onenglish.test',
            'is_active' => true,
            'sort_order' => 1,
        ])->assertRedirect(route('settings.payment-methods.index'));

        $this->assertDatabaseHas('payment_methods', [
            'currency' => PaymentMethod::CURRENCY_EUR,
            'label' => 'Transferencia EUR',
        ]);
    }

    public function test_non_master_admin_cannot_access_payment_method_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_master' => false]);

        $this->actingAs($admin)->get(route('settings.payment-methods.index'))->assertForbidden();
    }
}
