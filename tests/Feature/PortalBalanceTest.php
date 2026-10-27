<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExchangeRate;
use App\Models\Group;
use App\Models\MakeupRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use App\Support\FinanceReconcile;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Student $student;

    private User $studentUser;

    private User $representativeUser;

    private PaymentMethod $zelle;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00', config('app.timezone')));
        Storage::fake('public');
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->studentUser = User::factory()->create(['role' => 'student', 'email' => 'ana@test.com', 'campus_id' => $this->campus->id]);
        $this->student = Student::create(['campus_id' => $this->campus->id, 'user_id' => $this->studentUser->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'email' => 'ana@test.com', 'status' => 'active']);
        $this->representativeUser = User::factory()->create(['role' => 'representative', 'email' => 'marta@test.com', 'campus_id' => $this->campus->id]);
        $representative = Representative::create(['campus_id' => $this->campus->id, 'user_id' => $this->representativeUser->id, 'first_name' => 'Marta', 'last_name' => 'Gil', 'email' => 'marta@test.com']);
        $representative->students()->attach($this->student->id);
        $this->zelle = PaymentMethod::create(['currency' => PaymentMethod::CURRENCY_USD, 'method_type' => PaymentMethod::TYPE_ZELLE, 'label' => 'Zelle', 'email' => 'pay@test.com', 'is_active' => true, 'sort_order' => 1]);
        foreach (['USD' => 100, 'EUR' => 110] as $currency => $rate) {
            ExchangeRate::create(['currency' => $currency, 'rate' => $rate, 'effective_at' => now(), 'captured_at' => now(), 'source' => 'test']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function charge(string $concept, float $amount, string $currency = 'USD', array $extra = []): Charge
    {
        return Charge::create($extra + ['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'concept' => $concept, 'charge_type' => 'tuition', 'amount' => $amount, 'currency' => $currency, 'due_date' => '2026-11-05', 'status' => 'pending']);
    }

    private function seedLedger(): void
    {
        // $40 con $15 abonados, $25 anulado, €30 pendiente y un pago anulado que no debe restar.
        $october = $this->charge('Mensualidad octubre', 40);
        Payment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'charge_id' => $october->id, 'amount' => 15, 'currency' => 'USD', 'paid_at' => '2026-10-20', 'status' => 'confirmed']);
        Payment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'charge_id' => $october->id, 'amount' => 20, 'currency' => 'USD', 'paid_at' => '2026-10-21', 'status' => 'void', 'voided_at' => now()]);
        FinanceReconcile::syncCharge($october);
        $this->charge('Cargo anulado', 25, 'USD', ['status' => 'void', 'voided_at' => now()]);
        $this->charge('Taller en euros', 30, 'EUR');
    }

    private function submitProof(User $user, string $route, Charge $charge)
    {
        return $this->actingAs($user)->post(route($route, $charge), [
            'currency' => 'USD', 'original_amount' => 10, 'payment_method_id' => $this->zelle->id,
            'reference' => 'ZL-1', 'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);
    }

    public function test_balance_by_currency_ignores_voided_items(): void
    {
        $this->seedLedger();

        $this->assertSame(['EUR' => 30.0, 'USD' => 25.0], FinanceReconcile::outstandingByCurrency($this->student->charges()->get()));
    }

    public function test_student_portal_shows_balance_per_currency_with_bs_equivalent(): void
    {
        $this->seedLedger();

        $this->actingAs($this->studentUser)->get(route('portal.student'))
            ->assertOk()
            ->assertSeeInOrder(['Saldo pendiente', '€30,00', 'Bs 3.300,00', '$25,00', 'Bs 2.500,00', 'Tasa BCV del 26/10/2026'])
            ->assertDontSee('$90,00');
    }

    public function test_representative_portal_shows_the_same_balance(): void
    {
        $this->seedLedger();

        $this->actingAs($this->representativeUser)->get(route('portal.representative'))
            ->assertOk()
            ->assertSeeInOrder(['Saldo pendiente', '€30,00', '$25,00', 'Tasa BCV del 26/10/2026'])
            ->assertDontSee('$90,00')
            ->assertDontSee('$70,00');
    }

    public function test_second_proof_is_blocked_while_the_first_is_in_validation(): void
    {
        $charge = $this->charge('Mensualidad', 40);

        $this->submitProof($this->studentUser, 'portal.student.charges.payment', $charge)->assertSessionHasNoErrors();
        $this->submitProof($this->representativeUser, 'portal.representative.charges.payment', $charge)->assertSessionHasErrors('payment_proof');
        $this->submitProof($this->studentUser, 'portal.student.charges.payment', $charge)->assertSessionHasErrors('payment_proof');
        $this->assertSame(1, ChargePaymentRequest::query()->count());

        $this->actingAs($this->studentUser)->get(route('portal.student'))
            ->assertSee('En validación')
            ->assertDontSee('charge-pay-dialog-'.$charge->id, false);
        $this->actingAs($this->representativeUser)->get(route('portal.representative'))
            ->assertSee('En validación')
            ->assertDontSee(route('portal.representative.charges.payment', $charge), false);
    }

    public function test_rejected_proof_allows_sending_a_new_one(): void
    {
        $charge = $this->charge('Mensualidad', 40);
        $this->submitProof($this->studentUser, 'portal.student.charges.payment', $charge);
        ChargePaymentRequest::query()->update(['status' => ChargePaymentRequest::STATUS_REJECTED]);

        $this->submitProof($this->studentUser, 'portal.student.charges.payment', $charge)->assertSessionHasNoErrors();
        $this->assertSame(2, ChargePaymentRequest::query()->count());
    }

    public function test_makeup_charge_is_paid_only_from_the_makeup_section_in_student_portal(): void
    {
        $charge = $this->charge('Clase recuperativa', 10, 'USD', ['charge_type' => 'makeup']);

        $this->submitProof($this->studentUser, 'portal.student.charges.payment', $charge)->assertSessionHasErrors('payment_proof');
        $this->assertSame(0, ChargePaymentRequest::query()->count());

        $this->actingAs($this->studentUser)->get(route('portal.student'))
            ->assertOk()
            ->assertSee('Esta recuperativa se paga desde la pestaña')
            ->assertDontSee('charge-pay-dialog-'.$charge->id, false);
    }

    public function test_makeup_proof_in_validation_blocks_the_representative_form(): void
    {
        $charge = $this->charge('Clase recuperativa', 10, 'USD', ['charge_type' => 'makeup']);
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create(['campus_id' => $this->campus->id, 'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'HS'])->id, 'program_id' => $level->program_id, 'program_level_id' => $level->id, 'name' => 'HS5A', 'start_date' => '2026-09-01', 'status' => 'active']);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'name' => 'HS5A', 'status' => 'active', 'capacity' => 20]);
        $enrollment = Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $makeup = MakeupRequest::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'enrollment_id' => $enrollment->id, 'charge_id' => $charge->id, 'request_type' => MakeupRecoveryEngine::REQUEST_TYPE_MANUAL, 'price' => 10, 'currency' => 'USD', 'status' => MakeupRequest::STATUS_PENDING_VALIDATION]);
        $charge->update(['makeup_request_id' => $makeup->id]);

        $this->submitProof($this->representativeUser, 'portal.representative.charges.payment', $charge)->assertSessionHasErrors('payment_proof');
        $this->assertSame(0, ChargePaymentRequest::query()->count());
    }
}
