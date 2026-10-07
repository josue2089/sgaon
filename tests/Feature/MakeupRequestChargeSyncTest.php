<?php

namespace Tests\Feature;

use App\Mail\MakeupRecoveryApprovedMail;
use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\MakeupRequest;
use App\Models\PaymentMethod;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\User;
use App\Support\MakeupRecoveryEngine;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MakeupRequestChargeSyncTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private PaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->method = PaymentMethod::create([
            'currency' => PaymentCurrencyConverter::CURRENCY_USD,
            'method_type' => PaymentMethod::TYPE_ZELLE,
            'label' => 'Zelle USD',
            'email' => 'usd@example.com',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function absentMakeupRequest(string $name = 'Sergio'): MakeupRequest
    {
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => 'HS5A '.$name,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'name' => 'HS5A-'.$name, 'status' => 'active', 'capacity' => 30]);
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => $name, 'last_name' => 'León', 'email' => strtolower($name).'@student.test', 'status' => 'active']);
        $enrollment = Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $session = ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-09-14', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
        $record = AttendanceRecord::create(['class_session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => AttendanceRecord::STATUS_ABSENT]);

        return MakeupRecoveryEngine::syncForAttendanceRecord($record->fresh(['enrollment.student', 'enrollment.group.course', 'classSession']))->fresh('charge');
    }

    private function payFromFinance(MakeupRequest $request): void
    {
        $this->actingAs($this->admin)->post(route('finance.payments.store'), [
            'student_id' => $request->student_id,
            'charge_ids' => [$request->charge_id],
            'currency' => PaymentCurrencyConverter::CURRENCY_USD,
            'original_amount' => 10,
            'payment_method_id' => $this->method->id,
            'paid_at' => now()->toDateString(),
            'reference' => 'ZL-10',
        ])->assertRedirect();
    }

    public function test_paying_the_charge_from_finance_approves_the_request(): void
    {
        $request = $this->absentMakeupRequest();
        $this->assertSame(MakeupRequest::STATUS_PENDING_PAYMENT, $request->status);
        $this->assertSame(10.0, (float) $request->charge->amount);

        $this->payFromFinance($request);

        $request->refresh();
        $this->assertSame(MakeupRequest::STATUS_APPROVED_FOR_BOOKING, $request->status);
        $this->assertNotNull($request->payment_id);
        $this->assertNotNull($request->validated_at);
        Mail::assertSent(MakeupRecoveryApprovedMail::class, 1);

        $this->actingAs($this->admin)->get(route('makeups.index'))
            ->assertOk()
            ->assertSee('Aprobada para reservar')
            ->assertSee('Pagado');
    }

    public function test_voiding_the_payment_returns_request_to_pending_payment(): void
    {
        $request = $this->absentMakeupRequest();
        $this->payFromFinance($request);
        $payment = $request->fresh()->payment;

        $this->actingAs($this->admin)
            ->post(route('finance.payments.void', $payment), ['reason' => 'Registrado por error'])
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertSame(MakeupRequest::STATUS_PENDING_PAYMENT, $request->status);
        $this->assertNull($request->payment_id);
    }

    public function test_voiding_the_charge_cancels_the_request(): void
    {
        $request = $this->absentMakeupRequest();

        $this->actingAs($this->admin)
            ->post(route('finance.charges.void', $request->charge), ['reason' => 'No aplica recuperativa'])
            ->assertSessionHas('success');

        $this->assertSame(MakeupRequest::STATUS_CANCELLED, $request->fresh()->status);
    }

    public function test_backfill_command_approves_already_paid_requests_without_emails(): void
    {
        $paid = $this->absentMakeupRequest('Carlyn');
        $unpaid = $this->absentMakeupRequest('Bresly');

        // Simula un pago antiguo: cargo pagado sin que la solicitud se haya actualizado.
        Charge::query()->whereKey($paid->charge_id)->update(['status' => 'paid']);
        MakeupRequest::query()->whereKey($paid->id)->update(['status' => MakeupRequest::STATUS_PENDING_PAYMENT]);

        $this->artisan('makeups:sync-with-charges', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Solicitudes a actualizar: 1')
            ->assertSuccessful();
        $this->assertSame(MakeupRequest::STATUS_PENDING_PAYMENT, $paid->fresh()->status);

        $this->artisan('makeups:sync-with-charges')->assertSuccessful();

        $this->assertSame(MakeupRequest::STATUS_APPROVED_FOR_BOOKING, $paid->fresh()->status);
        $this->assertSame(MakeupRequest::STATUS_PENDING_PAYMENT, $unpaid->fresh()->status);
        Mail::assertNotSent(MakeupRecoveryApprovedMail::class);
    }

    public function test_daily_reconcile_fixes_stale_requests_silently(): void
    {
        $request = $this->absentMakeupRequest();

        // Pago antiguo registrado antes de este cambio: el cargo quedó pagado pero la solicitud no avanzó.
        $payment = \App\Models\Payment::create(['campus_id' => $this->campus->id, 'student_id' => $request->student_id, 'amount' => 10, 'currency' => 'USD', 'paid_at' => '2026-09-20', 'method' => 'Pago Movil', 'reference' => '0909', 'status' => 'confirmed']);
        \App\Models\PaymentAllocation::create(['payment_id' => $payment->id, 'charge_id' => $request->charge_id, 'amount_applied' => 10]);
        Charge::query()->whereKey($request->charge_id)->update(['status' => 'paid']);
        MakeupRequest::query()->whereKey($request->id)->update(['status' => MakeupRequest::STATUS_PENDING_PAYMENT]);

        $this->artisan('finance:reconcile-charges')->assertSuccessful();

        $request->refresh();
        $this->assertSame(MakeupRequest::STATUS_APPROVED_FOR_BOOKING, $request->status);
        $this->assertSame($payment->id, $request->payment_id);
        Mail::assertNotSent(MakeupRecoveryApprovedMail::class);
    }
}
