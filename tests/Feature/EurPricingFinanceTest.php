<?php

namespace Tests\Feature;

use App\Mail\ChargeDueReminderMail;
use App\Mail\ChargePendingMail;
use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExchangeRate;
use App\Models\Group;
use App\Models\PaymentMethod;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use App\Support\FinanceReconcile;
use App\Support\FinanceSummary;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EurPricingFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedPricingContext(float $priceEur = 240.0): array
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $program = Program::query()->where('code', 'HS')->firstOrFail();
        $level = ProgramLevel::query()->where('code', 'HS2B')->firstOrFail();
        $level->update(['base_price_eur' => $priceEur]);
        $academicLevel = AcademicLevel::create(['campus_id' => $campus->id, 'name' => 'High School']);
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $program->id,
            'program_level_id' => $level->id,
            'name' => 'HS2B',
            'code' => 'HS2B',
            'start_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);
        $group = Group::create([
            'campus_id' => $campus->id,
            'course_id' => $course->id,
            'name' => 'HS2B-G1',
            'period' => '2026-Q3',
            'status' => 'active',
        ]);
        $course->update(['managed_group_id' => $group->id]);
        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Josue',
            'last_name' => 'Ramos',
            'email' => 'josue@student.test',
            'status' => 'active',
        ]);

        return compact('campus', 'admin', 'program', 'level', 'course', 'group', 'student');
    }

    private function createPaymentMethods(): array
    {
        return [
            'eur' => PaymentMethod::create([
                'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
                'method_type' => PaymentMethod::TYPE_ZELLE,
                'label' => 'Transferencia EUR',
                'email' => 'eur@example.com',
                'is_active' => true,
                'sort_order' => 1,
            ]),
            'ves' => PaymentMethod::create([
                'currency' => PaymentCurrencyConverter::CURRENCY_VES,
                'method_type' => PaymentMethod::TYPE_PAGO_MOVIL,
                'label' => 'Pago móvil Bs',
                'phone' => '04141234567',
                'is_active' => true,
                'sort_order' => 1,
            ]),
        ];
    }

    private function seedEurRate(float $rate = 120.0): void
    {
        ExchangeRate::create([
            'currency' => 'EUR',
            'rate' => $rate,
            'effective_at' => now(),
            'captured_at' => now(),
            'source' => 'test',
        ]);
    }

    public function test_master_admin_can_store_program_level_price(): void
    {
        ['admin' => $admin, 'program' => $program] = $this->seedPricingContext();

        $response = $this->actingAs($admin)->post(route('program-levels.store', $program), [
            'name' => 'HS3',
            'code' => 'HS3',
            'sort_order' => 3,
            'program_total' => 6,
            'academic_hours' => 40,
            'base_price_eur' => 260,
            'reminder_days_before' => 5,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('program_levels', [
            'code' => 'HS3',
            'base_price_eur' => 260,
        ]);
    }

    public function test_enrollment_creates_eur_tuition_charge_idempotently(): void
    {
        Mail::fake();
        ['group' => $group, 'student' => $student] = $this->seedPricingContext();

        $enrollment = Enrollment::create([
            'campus_id' => $group->campus_id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);

        $first = app(EnrollmentBillingService::class)->createTuitionCharge($enrollment);
        $second = app(EnrollmentBillingService::class)->createTuitionCharge($enrollment);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('charges', 1);
        $this->assertSame(PaymentCurrencyConverter::CURRENCY_EUR, $first->currency);
        $this->assertEquals(240.0, (float) $first->amount);
        $this->assertSame('tuition', $first->charge_type);
        $this->assertSame('enrollment_auto', $first->origin);
        Mail::assertSent(ChargePendingMail::class);
    }

    public function test_partial_eur_payment_updates_balance_and_due_date(): void
    {
        ['admin' => $admin, 'student' => $student, 'group' => $group] = $this->seedPricingContext();
        $methods = $this->createPaymentMethods();

        $enrollment = Enrollment::create([
            'campus_id' => $group->campus_id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);
        $charge = app(EnrollmentBillingService::class)->createTuitionCharge($enrollment);
        $newDueDate = now()->addDays(30)->toDateString();

        $response = $this->actingAs($admin)->post('/finance/payments', [
            'student_id' => $student->id,
            'charge_ids' => [$charge->id],
            'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
            'original_amount' => 140,
            'payment_method_id' => $methods['eur']->id,
            'paid_at' => now()->toDateString(),
            'balance_due_date' => $newDueDate,
        ]);

        $response->assertRedirect();
        $payment = \App\Models\Payment::query()->first();
        $this->assertNotNull($payment);
        $this->assertEquals(140.0, (float) $payment->amount);
        $charge->refresh();
        $this->assertSame('partial', $charge->status);
        $this->assertEquals(100.0, FinanceReconcile::outstandingForCharge($charge));
        $this->assertSame($newDueDate, $charge->due_date?->toDateString());
    }

    public function test_ves_payment_against_eur_charge_converts_correctly(): void
    {
        ['admin' => $admin, 'student' => $student] = $this->seedPricingContext();
        $methods = $this->createPaymentMethods();
        $this->seedEurRate(100);

        $charge = Charge::create([
            'campus_id' => $student->campus_id,
            'student_id' => $student->id,
            'concept' => 'Mensualidad HS2',
            'amount' => 240,
            'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
            'due_date' => now()->addDays(5),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post('/finance/payments', [
            'student_id' => $student->id,
            'charge_ids' => [$charge->id],
            'currency' => PaymentCurrencyConverter::CURRENCY_VES,
            'original_amount' => 14000,
            'payment_method_id' => $methods['ves']->id,
            'paid_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertEquals(140.0, FinanceReconcile::paidTotalForCharge($charge->fresh()));
        $this->assertEquals(100.0, FinanceReconcile::outstandingForCharge($charge->fresh()));
    }

    public function test_exchange_rate_endpoint_supports_eur_currency(): void
    {
        ExchangeRate::create([
            'currency' => 'EUR',
            'rate' => 95.5,
            'effective_at' => now(),
            'captured_at' => now(),
            'source' => 'test',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson(route('finance.exchange-rate', ['currency' => 'EUR']))
            ->assertOk()
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('rate', 95.5);
    }

    public function test_payment_reminder_command_sends_mail_once_per_day(): void
    {
        Mail::fake();
        ['student' => $student] = $this->seedPricingContext();

        Charge::create([
            'campus_id' => $student->campus_id,
            'student_id' => $student->id,
            'concept' => 'Mensualidad HS2',
            'amount' => 100,
            'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pending',
        ]);

        $this->artisan('finance:send-payment-reminders')->assertSuccessful();
        $this->artisan('finance:send-payment-reminders')->assertSuccessful();

        Mail::assertSent(ChargeDueReminderMail::class, 1);
    }

    public function test_finance_summary_reports_invoiced_collected_and_projection(): void
    {
        ['admin' => $admin, 'student' => $student] = $this->seedPricingContext();

        $charge = Charge::create([
            'campus_id' => $student->campus_id,
            'student_id' => $student->id,
            'concept' => 'Mensualidad HS2',
            'amount' => 240,
            'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'partial',
        ]);

        \App\Models\Payment::create([
            'campus_id' => $student->campus_id,
            'student_id' => $student->id,
            'charge_id' => $charge->id,
            'amount' => 140,
            'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
            'original_amount' => 140,
            'paid_at' => now()->toDateString(),
            'status' => 'confirmed',
        ]);

        \App\Models\PaymentAllocation::create([
            'payment_id' => \App\Models\Payment::first()->id,
            'charge_id' => $charge->id,
            'amount_applied' => 140,
        ]);

        $summary = FinanceSummary::build($admin, null, null, PaymentCurrencyConverter::CURRENCY_EUR);
        $this->assertEquals(240.0, $summary['total_invoiced']);
        $this->assertEquals(140.0, $summary['total_collected']);
        $this->assertEquals(100.0, $summary['total_outstanding']);
        $this->assertGreaterThan(0, $summary['projection']->count());

        $this->actingAs($admin)
            ->get(route('finance.summary', ['currency' => 'EUR']))
            ->assertOk()
            ->assertSee('Resumen financiero')
            ->assertSee('Total facturado');
    }
}
