<?php

namespace Tests\Feature;

use App\Console\Commands\VoidLegacyRecurringCharges;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Payment;
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

    public function test_legacy_recurring_command_is_no_longer_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

        $this->assertStringContainsString('finance:reconcile-charges', $commands);
        $this->assertStringNotContainsString('finance:generate-recurring-charges', $commands);
    }
}
