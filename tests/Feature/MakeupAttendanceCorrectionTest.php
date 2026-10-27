<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Alert;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\MakeupRequest;
use App\Models\Payment;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\FinanceReconcile;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MakeupAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private Group $group;

    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00', config('app.timezone')));

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
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
        $this->group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'name' => 'HS5A', 'status' => 'active', 'capacity' => 30]);
        $this->session = ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $this->group->id, 'sequence' => 1, 'session_date' => '2026-10-20', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enroll(string $name, string $status = 'active', ?Group $group = null): Enrollment
    {
        $group ??= $this->group;
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => $name, 'last_name' => 'León', 'status' => 'active']);

        return Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => $status, 'progress' => 0]);
    }

    private function saveAttendance(Enrollment $enrollment, string $status)
    {
        return $this->actingAs($this->admin)->post(route('attendance.store'), [
            'class_session_id' => $this->session->id,
            'records' => [['enrollment_id' => $enrollment->id, 'status' => $status]],
        ]);
    }

    private function makeupFor(Enrollment $enrollment): MakeupRequest
    {
        return MakeupRequest::query()->where('enrollment_id', $enrollment->id)->with('charge')->sole();
    }

    public function test_charge_is_due_days_after_the_absence(): void
    {
        $enrollment = $this->enroll('Sergio');

        $this->saveAttendance($enrollment, 'absent')->assertSessionHasNoErrors();

        $charge = $this->makeupFor($enrollment)->charge;
        $this->assertSame('2026-10-27', $charge->due_date->toDateString());
        $this->assertSame('pending', FinanceReconcile::syncCharge($charge)->status, 'Vence hoy: todavía no es mora.');

        SystemSetting::putValue(MakeupRecoveryEngine::CHARGE_DUE_DAYS_KEY, '3');
        $other = $this->enroll('Luis');
        $this->saveAttendance($other, 'absent');
        $this->assertSame('2026-10-23', $this->makeupFor($other)->charge->due_date->toDateString());
    }

    public function test_correcting_absent_to_present_voids_the_charge_and_cancels_the_request(): void
    {
        $enrollment = $this->enroll('Sergio');
        $this->saveAttendance($enrollment, 'absent');
        $charge = $this->makeupFor($enrollment)->charge;
        $proof = ChargePaymentRequest::create(['campus_id' => $this->campus->id, 'student_id' => $enrollment->student_id, 'charge_id' => $charge->id, 'amount' => 10, 'currency' => 'USD', 'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION, 'submitted_at' => now(), 'proof_path' => 'x.jpg']);
        $this->assertTrue(Alert::query()->where('type', 'makeup_recovery')->where('status', 'open')->exists());

        $this->saveAttendance($enrollment, 'present')->assertSessionHasNoErrors();

        $charge->refresh();
        $this->assertNotNull($charge->voided_at);
        $this->assertSame((int) $this->admin->id, (int) $charge->voided_by);
        $this->assertSame(MakeupRequest::STATUS_CANCELLED, $this->makeupFor($enrollment)->status);
        $this->assertSame(ChargePaymentRequest::STATUS_REJECTED, $proof->fresh()->status);
        $this->assertFalse(Alert::query()->where('type', 'makeup_recovery')->where('status', 'open')->exists());
    }

    public function test_absent_again_after_correction_reopens_with_a_new_charge(): void
    {
        $enrollment = $this->enroll('Sergio');
        $this->saveAttendance($enrollment, 'absent');
        $firstCharge = $this->makeupFor($enrollment)->charge;
        $this->saveAttendance($enrollment, 'present');

        $this->saveAttendance($enrollment, 'absent');

        $makeup = $this->makeupFor($enrollment);
        $this->assertSame(MakeupRequest::STATUS_PENDING_PAYMENT, $makeup->status);
        $this->assertNotSame($firstCharge->id, $makeup->charge_id);
        $this->assertNull($makeup->charge->voided_at);

        // La conciliación del cargo viejo (anulado) no vuelve a cancelar la solicitud reabierta.
        FinanceReconcile::syncCharge($firstCharge->fresh());
        $this->assertSame(MakeupRequest::STATUS_PENDING_PAYMENT, $makeup->fresh()->status);
    }

    public function test_paid_makeup_is_not_touched_when_attendance_is_corrected(): void
    {
        $enrollment = $this->enroll('Sergio');
        $this->saveAttendance($enrollment, 'absent');
        $charge = $this->makeupFor($enrollment)->charge;
        Payment::create(['campus_id' => $this->campus->id, 'student_id' => $enrollment->student_id, 'charge_id' => $charge->id, 'amount' => 10, 'currency' => 'USD', 'paid_at' => '2026-10-21', 'status' => 'confirmed']);
        FinanceReconcile::syncCharge($charge->fresh());
        $this->assertSame(MakeupRequest::STATUS_APPROVED_FOR_BOOKING, $this->makeupFor($enrollment)->status);

        $this->saveAttendance($enrollment, 'present');

        $this->assertNull($charge->fresh()->voided_at);
        $this->assertSame('paid', $charge->fresh()->status);
        $this->assertSame(MakeupRequest::STATUS_APPROVED_FOR_BOOKING, $this->makeupFor($enrollment)->status);
    }

    public function test_attendance_list_hides_withdrawn_students_without_records(): void
    {
        $this->enroll('Activo');
        $this->enroll('Retirado', 'withdrawn');
        $withHistory = $this->enroll('Historial', 'withdrawn');
        AttendanceRecord::create(['class_session_id' => $this->session->id, 'enrollment_id' => $withHistory->id, 'status' => 'present']);

        $this->actingAs($this->admin)->get(route('attendance.index', ['class_session_id' => $this->session->id]))
            ->assertOk()
            ->assertSee('Activo')
            ->assertSee('Historial')
            ->assertDontSee('Retirado');
    }

    public function test_rejects_enrollments_from_another_group_or_withdrawn(): void
    {
        $otherGroup = Group::create(['campus_id' => $this->campus->id, 'course_id' => $this->group->course_id, 'name' => 'Otro', 'status' => 'active', 'capacity' => 30]);
        $foreign = $this->enroll('Ajeno', 'active', $otherGroup);
        $withdrawn = $this->enroll('Retirado', 'withdrawn');

        $this->saveAttendance($foreign, 'absent')->assertSessionHasErrors('records');
        $this->saveAttendance($withdrawn, 'absent')->assertSessionHasErrors('records');

        $this->assertSame(0, AttendanceRecord::query()->count());
        $this->assertSame(0, Charge::query()->count());
    }

    public function test_due_days_setting_can_be_saved(): void
    {
        $this->actingAs($this->admin)->put(route('settings.makeup-payment-instructions.update'), ['instructions' => 'Zelle', 'due_days' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame(5, MakeupRecoveryEngine::chargeDueDays());
        $this->actingAs($this->admin)->get(route('settings.makeup-payment-instructions.edit'))
            ->assertOk()
            ->assertSee('Días para pagar la recuperativa');
    }
}
