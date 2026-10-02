<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentVoidService;
use App\Support\FinanceReconcile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentVoidTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $this->student = Student::create([
            'campus_id' => $this->campus->id,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@student.test',
            'status' => 'active',
        ]);
    }

    private function charge(float $amount, string $dueDate): Charge
    {
        return Charge::create([
            'campus_id' => $this->campus->id,
            'student_id' => $this->student->id,
            'concept' => 'Cargo '.$dueDate,
            'amount' => $amount,
            'due_date' => $dueDate,
            'status' => 'pending',
        ]);
    }

    /**
     * @param  array<int, float>  $allocations  charge_id => monto
     */
    private function payment(array $allocations, string $reference = '0909'): Payment
    {
        $payment = Payment::create([
            'campus_id' => $this->campus->id,
            'student_id' => $this->student->id,
            'amount' => array_sum($allocations),
            'currency' => 'USD',
            'paid_at' => '2026-09-09',
            'method' => 'Zelle',
            'reference' => $reference,
            'status' => 'confirmed',
        ]);

        foreach ($allocations as $chargeId => $amount) {
            PaymentAllocation::create(['payment_id' => $payment->id, 'charge_id' => $chargeId, 'amount_applied' => $amount]);
            FinanceReconcile::syncCharge(Charge::find($chargeId));
        }

        Receipt::create([
            'campus_id' => $this->campus->id,
            'payment_id' => $payment->id,
            'receipt_number' => 'R-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
            'issued_at' => '2026-09-09',
        ]);

        return $payment;
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
    }

    public function test_admin_can_void_payment_and_charges_reopen(): void
    {
        $past = $this->charge(75, now()->subMonth()->toDateString());
        $future = $this->charge(75, now()->addMonth()->toDateString());
        $payment = $this->payment([$past->id => 75, $future->id => 75], 'COUTA GENERADA POR EL SISTEMA NO APLICA');
        $this->assertSame('paid', $past->fresh()->status);

        $admin = $this->master();

        $this->actingAs($admin)
            ->post(route('finance.payments.void', $payment), ['reason' => 'Pago registrado para saldar cuota que no aplica'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertNotNull($payment->voided_at);
        $this->assertSame($admin->id, $payment->voided_by);
        $this->assertSame('Pago registrado para saldar cuota que no aplica', $payment->void_reason);
        $this->assertSame(PaymentVoidService::VOID_STATUS, $payment->status);

        $this->assertSame('overdue', $past->fresh()->status);
        $this->assertSame('pending', $future->fresh()->status);
        $this->assertSame(75.0, FinanceReconcile::outstandingForCharge($past->fresh()));

        $this->assertTrue(AuditLog::query()->where('action', 'finance.payment.void')->exists());

        $this->actingAs($admin)
            ->get(route('finance.receipts.show', $payment->receipt))
            ->assertOk()
            ->assertSee('Pago ANULADO', false);

        $this->actingAs($admin)->get(route('finance.receipts.pdf', $payment->receipt))->assertOk();
        $this->actingAs($admin)->get(route('finance.index'))->assertOk()->assertSee('Anulado');
        $this->actingAs($admin)->get(route('students.show', $this->student))->assertOk()->assertSee('Anulado');
        $this->actingAs($admin)->get(route('finance.students.history', $this->student))->assertOk()->assertSee('Pago anulado');
    }

    public function test_voiding_one_of_two_payments_leaves_charge_partial(): void
    {
        $charge = $this->charge(100, now()->addMonth()->toDateString());
        $keep = $this->payment([$charge->id => 60]);
        $wrong = $this->payment([$charge->id => 40]);
        $this->assertSame('paid', $charge->fresh()->status);

        $this->actingAs($this->master())
            ->post(route('finance.payments.void', $wrong), ['reason' => 'Duplicado']);

        $this->assertSame('partial', $charge->fresh()->status);
        $this->assertSame(40.0, FinanceReconcile::outstandingForCharge($charge->fresh()));
        $this->assertNull($keep->fresh()->voided_at);
    }

    public function test_reason_is_required_and_payment_cannot_be_voided_twice(): void
    {
        $payment = $this->payment([$this->charge(75, '2026-09-05')->id => 75]);
        $admin = $this->master();

        $this->actingAs($admin)
            ->post(route('finance.payments.void', $payment), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertNull($payment->fresh()->voided_at);

        $this->actingAs($admin)->post(route('finance.payments.void', $payment), ['reason' => 'Error']);
        $this->actingAs($admin)
            ->post(route('finance.payments.void', $payment), ['reason' => 'Otra vez'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('Error', $payment->fresh()->void_reason);
    }

    public function test_admin_of_other_campus_and_teacher_cannot_void(): void
    {
        $payment = $this->payment([$this->charge(75, '2026-09-05')->id => 75]);
        $other = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);

        $otherAdmin = User::factory()->create(['role' => 'admin', 'campus_id' => $other->id, 'is_master' => false]);
        $this->actingAs($otherAdmin)
            ->post(route('finance.payments.void', $payment), ['reason' => 'x'])
            ->assertForbidden();

        $teacher = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]);
        $this->actingAs($teacher)
            ->post(route('finance.payments.void', $payment), ['reason' => 'x'])
            ->assertForbidden();

        $this->assertNull($payment->fresh()->voided_at);
    }

    public function test_voided_charge_has_no_outstanding_balance(): void
    {
        $charge = $this->charge(75, '2026-09-05');
        $charge->update(['voided_at' => now(), 'status' => 'void']);

        $this->assertSame(0.0, FinanceReconcile::outstandingForCharge($charge->fresh()));
    }
}
