<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\MakeupBooking;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\PaymentMethod;
use App\Models\ProgramLevel;
use App\Models\Receipt;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\MakeupRecoveryEngine;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MakeupManualRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private Teacher $teacher;

    private Student $student;

    private Enrollment $enrollment;

    private ClassSession $classSession;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $this->teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Baron', 'last_name' => 'Nava', 'status' => 'active']);

        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $this->teacher->id,
            'name' => 'HS5A - Horario: L-M 4.00 5.30',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'name' => 'HS5A', 'status' => 'active', 'capacity' => 30]);
        $this->student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Sergio', 'last_name' => 'León', 'email' => 'sergio@student.test', 'status' => 'active']);
        $this->enrollment = Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $this->classSession = ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-10-05', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'enrollment_id' => $this->enrollment->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => '2026-10-10',
            'starts_at' => '10:00',
            'ends_at' => '11:30',
            'price' => 10,
            'paid' => 0,
        ], $extra);
    }

    public function test_admin_registers_unpaid_makeup_without_absence(): void
    {
        $this->actingAs($this->admin)->get(route('students.show', $this->student))
            ->assertOk()
            ->assertSee('Registrar clase recuperativa');
        $this->actingAs($this->admin)->get(route('students.makeups.create', $this->student))
            ->assertOk()
            ->assertSee('Baron Nava');

        $this->actingAs($this->admin)
            ->post(route('students.makeups.store', $this->student), $this->payload())
            ->assertRedirect(route('students.show', $this->student))
            ->assertSessionHas('success', 'Clase recuperativa registrada. El cargo quedó pendiente de pago.');

        $request = MakeupRequest::query()->sole();
        $this->assertSame(MakeupRecoveryEngine::REQUEST_TYPE_MANUAL, $request->request_type);
        $this->assertNull($request->attendance_record_id);
        $this->assertSame(MakeupRequest::STATUS_BOOKED, $request->status);
        $this->assertSame(10.0, (float) $request->charge->amount);
        $this->assertSame('pending', $request->charge->status);

        $session = MakeupSession::query()->sole();
        $this->assertSame($this->teacher->id, (int) $session->teacher_id);
        $this->assertSame('2026-10-10', $session->session_date->toDateString());
        $this->assertSame('reserved', MakeupBooking::query()->sole()->status);

        $this->actingAs($this->admin)->get(route('students.show', $this->student))
            ->assertSee('Sin inasistencia (manual)')
            ->assertSee('10/10/2026 · 10:00 - 11:30')
            ->assertSee('Reservada');
        $this->actingAs($this->admin)->get(route('makeups.index'))->assertOk()->assertSee('Sergio');
    }

    public function test_reuses_absence_request_and_registers_payment(): void
    {
        $absence = AttendanceRecord::create(['class_session_id' => $this->classSession->id, 'enrollment_id' => $this->enrollment->id, 'status' => AttendanceRecord::STATUS_ABSENT]);
        $automatic = MakeupRecoveryEngine::syncForAttendanceRecord($absence->fresh(['enrollment.student', 'enrollment.group.course', 'classSession']));
        $method = PaymentMethod::create(['currency' => PaymentCurrencyConverter::CURRENCY_USD, 'method_type' => PaymentMethod::TYPE_ZELLE, 'label' => 'Zelle USD', 'email' => 'usd@example.com', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->post(route('students.makeups.store', $this->student), $this->payload([
                'attendance_record_id' => $absence->id,
                'medical_support_required' => 1,
                'price' => 5,
                'paid' => 1,
                'payment_method_id' => $method->id,
                'currency' => 'USD',
                'original_amount' => 5,
                'paid_at' => '2026-10-07',
                'reference' => 'ZL-55',
            ]))
            ->assertSessionHas('success', 'Clase recuperativa registrada y pago aplicado.');

        $this->assertSame(1, MakeupRequest::query()->count(), 'Se reutiliza la solicitud de la inasistencia.');
        $this->assertSame(1, Charge::query()->where('charge_type', 'makeup')->count(), 'No se duplica el cargo.');

        $request = $automatic->fresh('charge');
        $this->assertSame(MakeupRequest::STATUS_BOOKED, $request->status);
        $this->assertSame(5.0, (float) $request->charge->amount);
        $this->assertSame('paid', $request->charge->status);
        $this->assertTrue(Receipt::query()->exists());
    }

    public function test_absence_already_booked_is_rejected(): void
    {
        $absence = AttendanceRecord::create(['class_session_id' => $this->classSession->id, 'enrollment_id' => $this->enrollment->id, 'status' => AttendanceRecord::STATUS_ABSENT]);
        $this->actingAs($this->admin)->post(route('students.makeups.store', $this->student), $this->payload(['attendance_record_id' => $absence->id]));

        $this->actingAs($this->admin)
            ->post(route('students.makeups.store', $this->student), $this->payload(['attendance_record_id' => $absence->id, 'session_date' => '2026-10-17']))
            ->assertSessionHasErrors('attendance_record_id');

        $this->assertSame(1, MakeupSession::query()->count());
    }

    public function test_admin_of_other_campus_cannot_register(): void
    {
        $other = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'admin', 'campus_id' => $other->id, 'is_master' => false]);

        $this->actingAs($outsider)->get(route('students.makeups.create', $this->student))->assertForbidden();
        $this->actingAs($outsider)->post(route('students.makeups.store', $this->student), $this->payload())->assertForbidden();
        $this->assertFalse(MakeupRequest::query()->exists());
    }
}
