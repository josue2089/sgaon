<?php

namespace Tests\Feature;

use App\Mail\PaymentReceiptMail;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\User;
use App\Support\FinanceReconcile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Student $student;

    private PaymentMethod $zelle;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 10:00:00');
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $this->student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'email' => 'ana@test.com', 'status' => 'active']);
        $this->zelle = PaymentMethod::create(['currency' => PaymentMethod::CURRENCY_USD, 'method_type' => PaymentMethod::TYPE_ZELLE, 'label' => 'Zelle', 'email' => 'pay@test.com', 'is_active' => true, 'sort_order' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function charge(string $concept, float $amount, string $due = '2026-10-05'): Charge
    {
        return Charge::create(['campus_id' => $this->student->campus_id, 'student_id' => $this->student->id, 'concept' => $concept, 'charge_type' => 'tuition', 'amount' => $amount, 'currency' => 'USD', 'due_date' => $due, 'status' => 'pending']);
    }

    private function paymentRequest(Charge $charge, float $amount, string $reference = 'ZL-1'): ChargePaymentRequest
    {
        return ChargePaymentRequest::create([
            'campus_id' => $charge->campus_id, 'student_id' => $this->student->id, 'charge_id' => $charge->id,
            'amount' => $amount, 'currency' => 'USD', 'original_amount' => $amount,
            'payment_method_id' => $this->zelle->id, 'payment_method' => 'Zelle', 'reference' => $reference,
            'proof_path' => 'x.jpg', 'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION, 'submitted_at' => '2026-10-24 18:00:00',
        ]);
    }

    private function pay(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('finance.payments.store'), $overrides + [
            'student_id' => $this->student->id, 'currency' => 'USD', 'payment_method_id' => $this->zelle->id, 'paid_at' => '2026-10-26',
        ]);
    }

    public function test_split_payments_in_cents_leave_the_charge_paid(): void
    {
        Mail::fake();
        $charge = $this->charge('Mensualidad', 100);

        foreach ([33.33, 33.33, 33.34] as $i => $amount) {
            $this->pay(['charge_ids' => [$charge->id], 'original_amount' => $amount, 'reference' => 'R'.$i])->assertSessionHasNoErrors();
        }

        $this->assertSame('paid', $charge->fresh()->status);
        $this->assertSame(0.0, (float) FinanceReconcile::outstandingForCharge($charge->fresh()));
    }

    public function test_mail_failure_keeps_the_payment_and_receipt(): void
    {
        config(['mail.default' => 'failing']);
        config(['mail.mailers.failing' => ['transport' => 'failover', 'mailers' => []]]);
        $charge = $this->charge('Mensualidad', 40);

        $this->pay(['charge_ids' => [$charge->id], 'original_amount' => 40])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment = Payment::query()->sole();
        $this->assertNotNull($payment->receipt);
        $this->assertSame('paid', $charge->fresh()->status);
    }

    public function test_duplicate_reference_is_flagged_and_can_be_forced(): void
    {
        Mail::fake();
        $this->pay(['original_amount' => 10, 'reference' => 'ZL-777'])->assertSessionHasNoErrors();

        $this->pay(['original_amount' => 10, 'reference' => ' zl-777 '])->assertSessionHasErrors('reference');
        $this->assertSame(1, Payment::query()->count());

        $this->pay(['original_amount' => 10, 'reference' => 'ZL-777', 'allow_duplicate_reference' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, Payment::query()->count());
    }

    public function test_approving_twice_creates_one_payment_with_the_transfer_date(): void
    {
        Mail::fake();
        $charge = $this->charge('Mensualidad', 40);
        $request = $this->paymentRequest($charge, 40);

        $approve = fn () => $this->actingAs($this->admin)->patch(route('finance.payment-requests.review', $request), ['action' => 'approve', 'paid_at' => '2026-10-24']);
        $approve()->assertSessionHasNoErrors();
        $approve()->assertSessionHasErrors('action');

        $payment = Payment::query()->sole();
        $this->assertSame('2026-10-24', $payment->paid_at->toDateString());
        $this->assertSame('2026-10-24', $payment->receipt->issued_at->toDateString());
        $this->assertSame('paid', $charge->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.payment_request.approve')->exists());
        Mail::assertSent(PaymentReceiptMail::class, 1);
    }

    public function test_excess_goes_to_other_open_charges_and_is_never_dropped(): void
    {
        Mail::fake();
        $october = $this->charge('Mensualidad octubre', 40, '2026-10-05');
        $november = $this->charge('Mensualidad noviembre', 40, '2026-11-05');
        $request = $this->paymentRequest($october, 100);

        $this->actingAs($this->admin)->patch(route('finance.payment-requests.review', $request), ['action' => 'approve', 'paid_at' => '2026-10-24'])
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Mensualidad noviembre') && str_contains($message, 'sin aplicar'));

        $payment = Payment::query()->sole();
        $this->assertSame(100.0, (float) $payment->amount);
        $this->assertSame('paid', $october->fresh()->status);
        $this->assertSame('paid', $november->fresh()->status);
        $this->assertSame(80.0, (float) $payment->allocations()->sum('amount_applied'));
    }

    public function test_rejection_requires_a_reason(): void
    {
        Mail::fake();
        $request = $this->paymentRequest($this->charge('Mensualidad', 40), 40);

        $this->actingAs($this->admin)->patch(route('finance.payment-requests.review', $request), ['action' => 'reject'])
            ->assertSessionHasErrors('rejection_reason');
        $this->assertSame(ChargePaymentRequest::STATUS_PENDING_VALIDATION, $request->fresh()->status);

        $this->actingAs($this->admin)->patch(route('finance.payment-requests.review', $request), ['action' => 'reject', 'rejection_reason' => 'No se encontró la transferencia en la cuenta.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('No se encontró la transferencia en la cuenta.', $request->fresh()->rejection_reason);
    }

    public function test_forms_are_guarded_against_double_submit(): void
    {
        $charge = $this->charge('Mensualidad', 40);
        $request = $this->paymentRequest($charge, 40, 'ZL-1');
        $this->pay(['original_amount' => 5, 'reference' => 'ZL-1']);

        $this->actingAs($this->admin)->get(route('finance.index', ['tab' => 'comprobantes']))
            ->assertOk()
            ->assertSee('name="paid_at"', false)
            ->assertSee('data-guard-submit', false)
            ->assertSee('Atención: esta referencia ya está en el pago');

        $this->actingAs($this->admin)->get(route('finance.index', ['student_id' => $this->student->id]))
            ->assertOk()
            ->assertSee('data-finance-payment-form'."\n".'    data-guard-submit', false);
    }
}
