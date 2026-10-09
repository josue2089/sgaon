<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Alert;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Group;
use App\Models\PaymentMethod;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\User;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UxAuditBugsTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $siteAdmin;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-10-07 10:00:00');

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->siteAdmin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $this->student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Sergio', 'last_name' => 'León', 'email' => 'sergio@student.test', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function charge(string $concept, string $dueDate = '2026-10-20'): Charge
    {
        return Charge::create([
            'campus_id' => $this->campus->id,
            'student_id' => $this->student->id,
            'concept' => $concept,
            'charge_type' => 'other',
            'amount' => 10,
            'currency' => 'USD',
            'due_date' => $dueDate,
            'status' => 'pending',
        ]);
    }

    public function test_site_admin_manages_finance_from_student_page(): void
    {
        $this->actingAs($this->siteAdmin)->get(route('students.show', $this->student))
            ->assertOk()
            ->assertSee('Registrar pago');

        $this->actingAs($this->siteAdmin)->post(route('students.charges.store', $this->student), [
            'concept' => 'Material didáctico',
            'charge_type' => 'materials',
            'amount' => 15,
            'currency' => 'USD',
            'due_date' => '2026-10-15',
            'status' => 'pending',
        ])->assertRedirect();

        $this->assertTrue(Charge::query()->where('concept', 'Material didáctico')->exists());
    }

    public function test_admin_of_other_campus_cannot_add_charge(): void
    {
        $other = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'admin', 'campus_id' => $other->id, 'is_master' => false]);

        $this->actingAs($outsider)->post(route('students.charges.store', $this->student), [
            'concept' => 'No debería',
            'amount' => 15,
            'status' => 'pending',
        ])->assertForbidden();

        $this->assertFalse(Charge::query()->where('concept', 'No debería')->exists());
    }

    public function test_finance_payment_form_lists_charges_beyond_the_table_page(): void
    {
        foreach (range(1, 24) as $i) {
            $this->charge('Cargo '.$i, '2026-10-'.str_pad((string) min($i, 28), 2, '0', STR_PAD_LEFT));
        }
        $last = $this->charge('Cargo tardío', '2026-12-20');
        $this->charge('Cargo pagado', '2026-12-21')->update(['status' => 'paid']);

        $response = $this->actingAs($this->siteAdmin)->get(route('finance.index'))->assertOk();
        $response->assertSee('value="'.$last->id.'"', false);
        $response->assertDontSee('Cargo pagado — Saldo', false);
    }

    public function test_payment_success_message_links_to_receipt(): void
    {
        $charge = $this->charge('Mensualidad octubre');
        $method = PaymentMethod::create(['currency' => PaymentCurrencyConverter::CURRENCY_USD, 'method_type' => PaymentMethod::TYPE_ZELLE, 'label' => 'Zelle USD', 'email' => 'usd@example.com', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->siteAdmin)->post(route('finance.payments.store'), [
            'student_id' => $this->student->id,
            'charge_ids' => [$charge->id],
            'currency' => 'USD',
            'original_amount' => 10,
            'payment_method_id' => $method->id,
            'paid_at' => '2026-10-07',
            'reference' => 'ZL-1',
        ])->assertSessionHas('success_link', fn ($link) => str_contains($link['url'], '/finance/receipts/')
            && str_starts_with($link['label'], 'Ver recibo'));
    }

    public function test_dashboard_links_today_classes_to_attendance_and_translates_alerts(): void
    {
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => 'HS5A',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'name' => 'HS5A-G', 'status' => 'active', 'capacity' => 30]);
        $session = ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-10-07', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
        Alert::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'type' => 'finance', 'status' => 'open', 'message' => 'Saldo pendiente']);

        $this->actingAs($this->siteAdmin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('attendance.index', ['class_session_id' => $session->id]), false)
            ->assertSee('Tomar asistencia')
            ->assertSee('Pagos pendientes')
            ->assertDontSee('<strong>Finance</strong>', false);
    }

    public function test_site_admin_sees_holidays_in_navigation(): void
    {
        $this->actingAs($this->siteAdmin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Feriados y días sin clase')
            ->assertDontSee('Usuarios admin');
    }
}
