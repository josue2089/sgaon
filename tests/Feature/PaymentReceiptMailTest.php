<?php

namespace Tests\Feature;

use App\Mail\PaymentReceiptMail;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\PaymentMethod;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentReceiptMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_payment_sends_receipt_email_with_pdf(): void
    {
        Mail::fake();

        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $program = Program::query()->where('code', 'HS')->firstOrFail();
        $level = ProgramLevel::query()->where('code', 'HS2B')->firstOrFail();
        $level->update(['base_price_eur' => 240]);

        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@student.test',
            'status' => 'active',
        ]);

        $representative = Representative::create([
            'campus_id' => $campus->id,
            'first_name' => 'María',
            'last_name' => 'González',
            'email' => 'maria@rep.test',
        ]);
        $student->representatives()->attach($representative->id);

        $course = \App\Models\Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => \App\Models\AcademicLevel::create(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $program->id,
            'program_level_id' => $level->id,
            'name' => 'HS2B',
            'code' => 'HS2B',
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

        $enrollment = Enrollment::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);
        $charge = app(EnrollmentBillingService::class)->createTuitionCharge($enrollment);

        $method = PaymentMethod::create([
            'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
            'method_type' => PaymentMethod::TYPE_TRANSFERENCIA,
            'label' => 'Transferencia EUR',
            'email' => 'eur@example.com',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)->post(route('finance.payments.store'), [
            'student_id' => $student->id,
            'charge_ids' => [$charge->id],
            'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
            'original_amount' => 100,
            'payment_method_id' => $method->id,
            'paid_at' => now()->toDateString(),
        ])->assertRedirect();

        Mail::assertSent(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) {
            return count($mail->attachments()) === 1;
        });
    }

    public function test_master_admin_can_register_payment_from_student_profile(): void
    {
        Mail::fake();

        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Luis',
            'last_name' => 'Torres',
            'email' => 'luis@student.test',
            'status' => 'active',
        ]);

        $charge = Charge::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'concept' => 'Mensualidad prueba',
            'amount' => 75,
            'currency' => PaymentCurrencyConverter::CURRENCY_USD,
            'due_date' => now()->addDays(10),
            'status' => 'pending',
        ]);

        $method = PaymentMethod::create([
            'currency' => PaymentCurrencyConverter::CURRENCY_USD,
            'method_type' => PaymentMethod::TYPE_ZELLE,
            'label' => 'Zelle',
            'email' => 'zelle@example.com',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($master)
            ->post(route('students.payments.store', $student), [
                'charge_ids' => [$charge->id],
                'currency' => PaymentCurrencyConverter::CURRENCY_USD,
                'original_amount' => 75,
                'payment_method_id' => $method->id,
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect(route('students.show', $student));

        Mail::assertSent(PaymentReceiptMail::class);
        $this->assertDatabaseHas('payments', ['student_id' => $student->id, 'amount' => 75]);
    }

    public function test_master_admin_can_create_charge_from_student_profile(): void
    {
        Mail::fake();

        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Carla',
            'last_name' => 'Ruiz',
            'email' => 'carla@student.test',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->post(route('students.charges.store', $student), [
                'concept' => 'Materiales Q3',
                'charge_type' => 'materials',
                'amount' => 45,
                'currency' => PaymentCurrencyConverter::CURRENCY_USD,
                'due_date' => now()->addDays(15)->toDateString(),
                'status' => 'pending',
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertDatabaseHas('charges', [
            'student_id' => $student->id,
            'concept' => 'Materiales Q3',
            'amount' => 45,
            'currency' => PaymentCurrencyConverter::CURRENCY_USD,
            'status' => 'pending',
        ]);
    }
}
