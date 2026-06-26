<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\User;
use App\Services\Bcv\ExchangeRateService;
use App\Support\FinanceReconcile;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DualCurrencyPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function seedCampusStudentCharge(float $amount = 100.0): array
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id]);
        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'status' => 'active',
        ]);
        $charge = Charge::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'concept' => 'Mensualidad',
            'amount' => $amount,
            'due_date' => now()->addDays(5),
            'status' => 'pending',
        ]);

        return compact('campus', 'admin', 'student', 'charge');
    }

    private function seedBcvRate(float $rate = 100.0): void
    {
        ExchangeRate::create([
            'currency' => 'USD',
            'rate' => $rate,
            'effective_at' => now(),
            'captured_at' => now(),
            'source' => 'test',
        ]);
    }

    private function createPaymentMethods(): array
    {
        $usd = PaymentMethod::create([
            'currency' => PaymentMethod::CURRENCY_USD,
            'method_type' => PaymentMethod::TYPE_ZELLE,
            'label' => 'Zelle ON English',
            'email' => 'pay@example.com',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $ves = PaymentMethod::create([
            'currency' => PaymentMethod::CURRENCY_VES,
            'method_type' => PaymentMethod::TYPE_PAGO_MOVIL,
            'label' => 'Pago móvil BNC',
            'phone' => '04141234567',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return compact('usd', 'ves');
    }

    public function test_admin_can_register_payment_in_ves_converted_to_usd(): void
    {
        ['admin' => $admin, 'student' => $student, 'charge' => $charge] = $this->seedCampusStudentCharge();
        $this->seedBcvRate(100);
        ['ves' => $vesMethod] = $this->createPaymentMethods();

        $response = $this->actingAs($admin)->post('/finance/payments', [
            'student_id' => $student->id,
            'charge_ids' => [$charge->id],
            'currency' => PaymentCurrencyConverter::CURRENCY_VES,
            'original_amount' => 10000,
            'payment_method_id' => $vesMethod->id,
            'paid_at' => now()->toDateString(),
            'reference' => 'PM-001',
        ]);

        $response->assertRedirect();
        $payment = Payment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentCurrencyConverter::CURRENCY_VES, $payment->currency);
        $this->assertEquals(10000.0, (float) $payment->original_amount);
        $this->assertEquals(100.0, (float) $payment->amount);
        $this->assertEquals(100.0, (float) $payment->exchange_rate);
        $this->assertSame('paid', $charge->fresh()->status);
    }

    public function test_portal_submission_in_ves_creates_request_with_persisted_rate(): void
    {
        ['campus' => $campus, 'student' => $student, 'charge' => $charge] = $this->seedCampusStudentCharge();
        $this->seedBcvRate(50);
        ['ves' => $vesMethod] = $this->createPaymentMethods();

        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'ana@student.test',
            'campus_id' => $campus->id,
        ]);
        $student->update(['user_id' => $user->id, 'email' => 'ana@student.test']);

        Storage::fake('public');

        $response = $this->actingAs($user)->post(route('portal.student.charges.payment', $charge), [
            'currency' => PaymentCurrencyConverter::CURRENCY_VES,
            'original_amount' => 2500,
            'payment_method_id' => $vesMethod->id,
            'reference' => '123456',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $response->assertRedirect();
        $request = ChargePaymentRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertEquals(50.0, (float) $request->amount);
        $this->assertEquals(2500.0, (float) $request->original_amount);
        $this->assertEquals(50.0, (float) $request->exchange_rate);
    }

    public function test_approving_ves_request_creates_payment_with_stored_conversion(): void
    {
        ['admin' => $admin, 'student' => $student, 'charge' => $charge] = $this->seedCampusStudentCharge(50.0);
        ['ves' => $vesMethod] = $this->createPaymentMethods();

        $paymentRequest = ChargePaymentRequest::create([
            'campus_id' => $charge->campus_id,
            'student_id' => $student->id,
            'charge_id' => $charge->id,
            'amount' => 50.0,
            'currency' => PaymentCurrencyConverter::CURRENCY_VES,
            'original_amount' => 5000.0,
            'exchange_rate' => 100.0,
            'exchange_rate_effective_at' => now(),
            'payment_method_id' => $vesMethod->id,
            'payment_method' => $vesMethod->label,
            'reference' => 'ABC',
            'proof_path' => 'charge-payment-requests/'.$charge->id.'/proof.jpg',
            'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('finance.payment-requests.review', $paymentRequest), [
            'action' => 'approve',
        ]);

        $response->assertRedirect();
        $payment = Payment::query()->first();
        $this->assertNotNull($payment);
        $this->assertEquals(50.0, (float) $payment->amount);
        $this->assertEquals(5000.0, (float) $payment->original_amount);
        $this->assertEquals(100.0, (float) $payment->exchange_rate);
        $this->assertSame('paid', $charge->fresh()->status);
    }

    public function test_usd_payment_regression_keeps_reconcile_behavior(): void
    {
        ['admin' => $admin, 'student' => $student, 'charge' => $charge] = $this->seedCampusStudentCharge(80);
        ['usd' => $usdMethod] = $this->createPaymentMethods();

        $this->actingAs($admin)->post('/finance/payments', [
            'student_id' => $student->id,
            'charge_ids' => [$charge->id],
            'currency' => PaymentCurrencyConverter::CURRENCY_USD,
            'original_amount' => 30,
            'payment_method_id' => $usdMethod->id,
            'paid_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertEquals(30.0, FinanceReconcile::paidTotalForCharge($charge->fresh()));
        $this->assertEquals(50.0, FinanceReconcile::outstandingForCharge($charge->fresh()));
    }
}
