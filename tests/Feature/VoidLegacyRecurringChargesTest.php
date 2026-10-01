<?php

namespace Tests\Feature;

use App\Console\Commands\VoidLegacyRecurringCharges;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Support\FinanceReconcile;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoidLegacyRecurringChargesTest extends TestCase
{
    use RefreshDatabase;

    private function seedCharges(): array
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@student.test',
            'status' => 'active',
        ]);

        $legacy = fn (string $month, string $status) => Charge::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'concept' => 'Mensualidad académica '.$month.' · HS2B-G1',
            'amount' => 75,
            'due_date' => $month.'-05',
            'status' => $status,
            'notes' => 'Auto-generado por inscripción activa #1',
        ]);

        $legacyUnpaid = $legacy('2026-09', 'overdue');
        $legacyPaid = $legacy('2026-08', 'paid');
        Payment::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'charge_id' => $legacyPaid->id,
            'amount' => 75,
            'paid_at' => '2026-08-06',
            'method' => 'cash',
        ]);

        $tuition = Charge::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'concept' => 'Mensualidad HS2B — HS2B',
            'charge_type' => 'tuition',
            'origin' => 'enrollment_auto',
            'amount' => 240,
            'currency' => 'EUR',
            'due_date' => '2026-09-10',
            'status' => 'overdue',
        ]);

        return compact('legacyUnpaid', 'legacyPaid', 'tuition');
    }

    public function test_dry_run_lists_charges_without_modifying_them(): void
    {
        ['legacyUnpaid' => $legacyUnpaid] = $this->seedCharges();

        $this->artisan('finance:void-legacy-recurring-charges', ['--dry-run' => true])
            ->expectsOutputToContain('Cargos legacy sin pagos a anular: 1')
            ->expectsOutputToContain('Cargos legacy con pagos (revisar manualmente, no se anulan): 1')
            ->assertSuccessful();

        $this->assertNull($legacyUnpaid->fresh()->voided_at);
        $this->assertSame('overdue', $legacyUnpaid->fresh()->status);
    }

    public function test_voids_only_unpaid_legacy_charges(): void
    {
        ['legacyUnpaid' => $legacyUnpaid, 'legacyPaid' => $legacyPaid, 'tuition' => $tuition] = $this->seedCharges();

        $this->artisan('finance:void-legacy-recurring-charges')->assertSuccessful();

        $legacyUnpaid->refresh();
        $this->assertNotNull($legacyUnpaid->voided_at);
        $this->assertSame(VoidLegacyRecurringCharges::VOID_STATUS, $legacyUnpaid->status);
        $this->assertStringContainsString(VoidLegacyRecurringCharges::VOID_REASON, $legacyUnpaid->notes);

        $this->assertNull($legacyPaid->fresh()->voided_at);
        $this->assertNull($tuition->fresh()->voided_at);
        $this->assertSame('overdue', $tuition->fresh()->status);

        // La conciliación diaria no debe reabrir cargos anulados.
        FinanceReconcile::syncCharge($legacyUnpaid);
        $this->assertSame(VoidLegacyRecurringCharges::VOID_STATUS, $legacyUnpaid->fresh()->status);
    }

    public function test_fake_payments_are_voided_and_free_their_legacy_charges(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Luis',
            'last_name' => 'Gómez',
            'email' => 'luis@student.test',
            'status' => 'active',
        ]);

        $legacy = fn (string $month) => Charge::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'concept' => 'Mensualidad académica '.$month.' · HS1B',
            'amount' => 75,
            'due_date' => $month.'-05',
            'status' => 'paid',
            'notes' => 'Auto-generado por inscripción activa #2',
        ]);
        $tuition = Charge::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'concept' => 'Mensualidad HS1B',
            'charge_type' => 'tuition',
            'amount' => 240,
            'currency' => 'EUR',
            'due_date' => '2026-09-10',
            'status' => 'partial',
        ]);

        $aug = $legacy('2026-08');
        $sep = $legacy('2026-09');
        $oct = $legacy('2026-10');
        $nov = $legacy('2026-11');

        $payment = function (string $reference, array $allocations) use ($campus, $student): Payment {
            $payment = Payment::create([
                'campus_id' => $campus->id,
                'student_id' => $student->id,
                'amount' => array_sum($allocations),
                'paid_at' => '2026-09-25',
                'method' => 'Zelle',
                'reference' => $reference,
                'status' => 'confirmed',
            ]);
            foreach ($allocations as $chargeId => $amount) {
                PaymentAllocation::create(['payment_id' => $payment->id, 'charge_id' => $chargeId, 'amount_applied' => $amount]);
            }

            return $payment;
        };

        $fake = $payment('COUTA GENERADA POR EL SISTEMA NO APLICA', [$aug->id => 75, $sep->id => 75]);
        $fakeMixed = $payment('CUOTA GENERADA POR EL SISTEMA NO APLICA', [$oct->id => 75, $tuition->id => 50]);
        $real = $payment('0909', [$nov->id => 75]);

        $this->artisan('finance:void-legacy-recurring-charges', ['--fake-payments' => true, '--dry-run' => true])
            ->expectsOutputToContain('Pagos "NO APLICA" a anular: 1')
            ->expectsOutputToContain('Cargos legacy sin pagos a anular: 2')
            ->assertSuccessful();
        $this->assertNull($fake->fresh()->voided_at);
        $this->assertNull($aug->fresh()->voided_at);

        $this->artisan('finance:void-legacy-recurring-charges', ['--fake-payments' => true])->assertSuccessful();

        $this->assertNotNull($fake->fresh()->voided_at);
        $this->assertSame(VoidLegacyRecurringCharges::VOID_STATUS, $fake->fresh()->status);
        $this->assertNotNull($aug->fresh()->voided_at);
        $this->assertNotNull($sep->fresh()->voided_at);

        // Pago "NO APLICA" que también tocó la mensualidad real y pago real: quedan para revisión manual.
        $this->assertNull($fakeMixed->fresh()->voided_at);
        $this->assertNull($oct->fresh()->voided_at);
        $this->assertNull($real->fresh()->voided_at);
        $this->assertNull($nov->fresh()->voided_at);
        $this->assertNull($tuition->fresh()->voided_at);
    }

    public function test_voided_payments_do_not_count_as_paid(): void
    {
        ['legacyPaid' => $legacyPaid] = $this->seedCharges();

        Payment::query()->where('charge_id', $legacyPaid->id)->update(['voided_at' => now()]);

        $this->assertSame(0.0, FinanceReconcile::paidTotalForCharge($legacyPaid->fresh()));
    }

    public function test_legacy_recurring_command_is_no_longer_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

        $this->assertStringContainsString('finance:reconcile-charges', $commands);
        $this->assertStringNotContainsString('finance:generate-recurring-charges', $commands);
    }
}
