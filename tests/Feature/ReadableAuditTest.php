<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** #451: auditoría en español, filtrable, e historial del alumno con dinero e inscripciones. */
class ReadableAuditTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true, 'name' => 'Josefina Admin']);
    }

    private function log(string $action, $model, array $payload, ?User $user = null, string $at = '2026-11-10 10:00:00'): AuditLog
    {
        $log = AuditLog::create(['user_id' => ($user ?? $this->master)->id, 'action' => $action, 'auditable_type' => $model ? $model::class : null, 'auditable_id' => $model?->id, 'payload' => $payload]);
        $log->forceFill(['created_at' => $at])->save();

        return $log;
    }

    public function test_labels_and_changes_are_readable(): void
    {
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Test', 'status' => 'active']);
        $log = $this->log('students.move_to_historical', $student, ['previous_status' => 'active', 'new_status' => 'withdrawn', 'password' => 'secreto']);

        $this->assertSame('Pasó un alumno a histórico', AuditLabel::action('students.move_to_historical'));
        $this->assertSame('Alumno #'.$student->id, AuditLabel::entity(Student::class, $student->id));
        $this->assertSame('Algo raro', AuditLabel::action('algo_raro'));
        $changes = AuditLabel::changes($log);
        $this->assertSame('Estado', $changes[0]['field']);
        $this->assertNotSame('withdrawn', $changes[0]['to'], 'El estado se muestra traducido.');
        $this->assertNotContains('secreto', array_column($changes, 'to'), 'Las claves nunca se muestran.');
    }

    public function test_audit_report_filters_by_action_user_entity_and_date(): void
    {
        $other = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true, 'name' => 'Pedro Otro']);
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Test', 'status' => 'active']);
        $charge = Charge::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'concept' => 'Mensualidad julio', 'amount' => 40, 'due_date' => '2026-07-05', 'status' => 'overdue']);
        $this->log('finance.charge.void', $charge, ['reason' => 'Pagada fuera del sistema'], null, '2026-11-12 09:00:00');
        $this->log('student.update', $student, ['first_name' => 'Ana María'], $other, '2026-11-01 09:00:00');

        $this->actingAs($this->master)->get(route('reports.audit'))
            ->assertOk()->assertSee('Anuló un cargo')->assertSee('Pagada fuera del sistema')->assertSee('Editó un alumno')
            ->assertDontSee('>finance.charge.void<', false)->assertDontSee('{&quot;', false);

        $this->actingAs($this->master)->get(route('reports.audit', ['action' => 'finance.*']))
            ->assertOk()->assertSee('Anuló un cargo')->assertDontSee('Ana María');
        $this->actingAs($this->master)->get(route('reports.audit', ['user_id' => $other->id]))
            ->assertOk()->assertSee('Ana María')->assertDontSee('Pagada fuera del sistema');
        $this->actingAs($this->master)->get(route('reports.audit', ['entity' => 'Charge']))
            ->assertOk()->assertSee('Pagada fuera del sistema')->assertDontSee('Ana María');
        $this->actingAs($this->master)->get(route('reports.audit', ['from' => '2026-11-10', 'to' => '2026-11-30']))
            ->assertOk()->assertSee('Pagada fuera del sistema')->assertDontSee('Ana María');
    }

    public function test_student_history_includes_money_and_enrollments(): void
    {
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Test', 'status' => 'active']);
        $other = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Beto', 'last_name' => 'Test', 'status' => 'active']);
        $charge = Charge::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'concept' => 'Mensualidad julio', 'amount' => 40, 'due_date' => '2026-07-05', 'status' => 'overdue']);
        $payment = Payment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'charge_id' => $charge->id, 'amount' => 40, 'currency' => 'USD', 'paid_at' => '2026-07-04', 'status' => 'completed']);
        $otherCharge = Charge::create(['campus_id' => $this->campus->id, 'student_id' => $other->id, 'concept' => 'Ajeno', 'amount' => 40, 'due_date' => '2026-07-05', 'status' => 'overdue']);
        $this->log('finance.charge.create', $charge, ['concept' => 'Mensualidad julio']);
        $this->log('finance.payment.create', $payment, ['reference' => 'REF-777']);
        $this->log('finance.charge.void', $otherCharge, ['reason' => 'Motivo ajeno']);

        $this->actingAs($this->master)->get(route('students.show', $student))
            ->assertOk()->assertSee('Creó un cargo')->assertSee('Registró un pago')->assertSee('REF-777')
            ->assertDontSee('Motivo ajeno');
    }
}
