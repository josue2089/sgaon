<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanceTabsTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Campus $other;

    private User $master;

    private Student $ana;

    private Student $luis;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->other = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $this->master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->ana = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'status' => 'active']);
        $this->luis = Student::create(['campus_id' => $this->other->id, 'first_name' => 'Luis', 'last_name' => 'Gil', 'status' => 'active']);

        $this->charge($this->ana, 'Mensualidad agosto', '2026-08-05', 'overdue');
        $this->charge($this->ana, 'Mensualidad octubre', '2026-10-20', 'pending');
        $this->charge($this->ana, 'Inscripción pagada', '2026-09-01', 'paid');
        $this->charge($this->luis, 'Mensualidad Cascada', '2026-10-05', 'overdue');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function charge(Student $student, string $concept, string $due, string $status): Charge
    {
        return Charge::create(['campus_id' => $student->campus_id, 'student_id' => $student->id, 'concept' => $concept, 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => $due, 'status' => $status]);
    }

    public function test_por_cobrar_lists_open_charges_and_filters(): void
    {
        $this->actingAs($this->master)->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Mensualidad agosto')
            ->assertSee('Mensualidad Cascada')
            ->assertDontSee('Inscripción pagada');

        $this->actingAs($this->master)->get(route('finance.index', ['campus_id' => $this->campus->id]))
            ->assertSee('Mensualidad agosto')
            ->assertDontSee('Mensualidad Cascada');

        $this->actingAs($this->master)->get(route('finance.index', ['status' => 'overdue', 'month' => '2026-08']))
            ->assertSee('Mensualidad agosto')
            ->assertDontSee('Mensualidad octubre')
            ->assertDontSee('Mensualidad Cascada');

        $this->actingAs($this->master)->get(route('finance.index', ['status' => 'paid']))
            ->assertSee('Inscripción pagada');
    }

    public function test_payments_and_proofs_tabs_load_their_own_data(): void
    {
        Payment::create(['campus_id' => $this->campus->id, 'student_id' => $this->ana->id, 'amount' => 40, 'currency' => 'USD', 'paid_at' => '2026-10-10', 'method' => 'Zelle', 'reference' => 'ZL-77', 'status' => 'confirmed']);
        $charge = Charge::query()->where('concept', 'Mensualidad octubre')->first();
        ChargePaymentRequest::create(['campus_id' => $this->campus->id, 'student_id' => $this->ana->id, 'charge_id' => $charge->id, 'amount' => 40, 'currency' => 'USD', 'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION, 'submitted_at' => now(), 'proof_path' => 'proofs/x.pdf', 'proof_original_name' => 'x.pdf', 'proof_mime_type' => 'application/pdf', 'proof_file_size' => 100]);

        $this->actingAs($this->master)->get(route('finance.index', ['tab' => 'pagos']))
            ->assertOk()
            ->assertSee('Pagos y recibos')
            ->assertSee('2026-10-10')
            ->assertDontSee('Cuentas por cobrar');

        $this->actingAs($this->master)->get(route('finance.index', ['tab' => 'comprobantes']))
            ->assertOk()
            ->assertSee('Comprobantes enviados desde el portal')
            ->assertSee('<span class="portal-tab-badge">1</span>', false)
            ->assertSee('Mensualidad octubre');
    }

    public function test_page_does_not_load_all_students_until_one_is_chosen(): void
    {
        $this->actingAs($this->master)->get(route('finance.index'))
            ->assertOk()
            ->assertSee('id="finance-pick-student"', false)
            ->assertSee('data-result-url=', false)
            ->assertDontSee('id="finance-payment-form"', false)
            ->assertDontSee('id="finance-charge-form"', false);

        $this->actingAs($this->master)->get(route('finance.index', ['student_id' => $this->ana->id, 'open' => 'payment']))
            ->assertOk()
            ->assertSee('Viendo solo a <strong>Ana Pérez</strong>', false)
            ->assertDontSee('#'.$this->ana->id.'<', false)
            ->assertSee('id="finance-payment-form"', false)
            ->assertSee('<input type="hidden" name="student_id" value="'.$this->ana->id.'">', false)
            ->assertSee('id="finance-payment-modal" class="ui-modal ui-modal--lg" data-open-on-load="1"', false)
            ->assertDontSee('Mensualidad Cascada');
    }

    public function test_site_admin_cannot_focus_a_student_from_another_campus(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);

        $this->actingAs($admin)->get(route('finance.index', ['student_id' => $this->luis->id]))
            ->assertOk()
            ->assertDontSee('Luis Gil')
            ->assertDontSee('id="finance-payment-form"', false);
    }

    public function test_validation_messages_are_in_spanish(): void
    {
        $this->actingAs($this->master)
            ->from(route('finance.index', ['student_id' => $this->ana->id]))
            ->post(route('finance.payments.store'), ['student_id' => $this->ana->id, 'currency' => 'USD', 'original_amount' => 10, 'paid_at' => '2026-10-13'])
            ->assertSessionHasErrors(['payment_method_id' => 'El método de pago es obligatorio.']);
    }
}
