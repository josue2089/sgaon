<?php

namespace Tests\Feature;

use App\Mail\ChargeDueReminderMail;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\User;
use App\Services\ChargeVoidService;
use App\Support\FinanceReconcile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChargeVoidTest extends TestCase
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

    private function charge(string $concept, string $dueDate, float $amount = 75): Charge
    {
        return Charge::create([
            'campus_id' => $this->campus->id,
            'student_id' => $this->student->id,
            'concept' => $concept,
            'amount' => $amount,
            'due_date' => $dueDate,
            'status' => 'pending',
        ]);
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
    }

    public function test_admin_can_void_pending_charge(): void
    {
        $charge = $this->charge('Mensualidad académica 2026-10 · HS1B', now()->subDays(3)->toDateString());
        $kept = $this->charge('Clase recuperativa', now()->addDays(10)->toDateString(), 10);
        $admin = $this->master();

        $this->actingAs($admin)
            ->post(route('finance.charges.void', $charge), ['reason' => 'Cuota que no corresponde'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $charge->refresh();
        $this->assertNotNull($charge->voided_at);
        $this->assertSame($admin->id, $charge->voided_by);
        $this->assertSame('Cuota que no corresponde', $charge->void_reason);
        $this->assertSame(ChargeVoidService::VOID_STATUS, $charge->status);
        $this->assertSame(0.0, FinanceReconcile::outstandingForCharge($charge));
        $this->assertTrue(AuditLog::query()->where('action', 'finance.charge.void')->exists());

        // La conciliación diaria no lo reabre.
        FinanceReconcile::syncCharge($charge);
        $this->assertSame(ChargeVoidService::VOID_STATUS, $charge->fresh()->status);

        $this->actingAs($admin)->get(route('finance.index'))
            ->assertOk()
            ->assertDontSee('Mensualidad académica 2026-10 · HS1B')
            ->assertSee($kept->concept);
        $this->actingAs($admin)->get(route('finance.index', ['anulados' => 1]))
            ->assertOk()
            ->assertSee('Mensualidad académica 2026-10 · HS1B')
            ->assertSee('Cuota que no corresponde');
        $this->actingAs($admin)->get(route('students.show', $this->student))
            ->assertOk()
            ->assertSee('Cuota que no corresponde');
        $this->actingAs($admin)->get(route('finance.students.history', $this->student))
            ->assertOk()
            ->assertSee('Cargo anulado');
    }

    public function test_charge_with_payments_requires_voiding_payment_first(): void
    {
        $charge = $this->charge('Mensualidad académica 2026-09 · HS1B', '2026-09-05');
        $payment = Payment::create([
            'campus_id' => $this->campus->id,
            'student_id' => $this->student->id,
            'amount' => 75,
            'currency' => 'USD',
            'paid_at' => '2026-09-09',
            'method' => 'Pago Movil',
            'reference' => '0909',
            'status' => 'confirmed',
        ]);
        PaymentAllocation::create(['payment_id' => $payment->id, 'charge_id' => $charge->id, 'amount_applied' => 75]);
        $admin = $this->master();

        $this->actingAs($admin)
            ->post(route('finance.charges.void', $charge), ['reason' => 'No corresponde'])
            ->assertSessionHasErrors('reason');
        $this->assertNull($charge->fresh()->voided_at);

        $this->actingAs($admin)->post(route('finance.payments.void', $payment), ['reason' => 'Saldo falso']);
        $this->actingAs($admin)
            ->post(route('finance.charges.void', $charge), ['reason' => 'No corresponde'])
            ->assertSessionHas('success');
        $this->assertNotNull($charge->fresh()->voided_at);
    }

    public function test_admin_of_other_campus_and_teacher_cannot_void(): void
    {
        $charge = $this->charge('Cargo', '2026-09-05');
        $other = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);

        $this->actingAs(User::factory()->create(['role' => 'admin', 'campus_id' => $other->id, 'is_master' => false]))
            ->post(route('finance.charges.void', $charge), ['reason' => 'x'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]))
            ->post(route('finance.charges.void', $charge), ['reason' => 'x'])
            ->assertForbidden();

        $this->assertNull($charge->fresh()->voided_at);
    }

    public function test_voided_charge_gets_no_payment_reminder(): void
    {
        Mail::fake();
        $charge = $this->charge('Cuota', now()->addDays(3)->toDateString());
        $valid = $this->charge('Mensualidad', now()->addDays(3)->toDateString());

        $this->actingAs($this->master())->post(route('finance.charges.void', $charge), ['reason' => 'No corresponde']);
        $this->artisan('finance:send-payment-reminders')->assertSuccessful();

        Mail::assertSent(ChargeDueReminderMail::class, 1);
        Mail::assertSent(ChargeDueReminderMail::class, fn (ChargeDueReminderMail $mail) => $mail->charge->is($valid));
    }
}
