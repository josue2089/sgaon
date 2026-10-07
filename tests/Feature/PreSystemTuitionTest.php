<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Period;
use App\Models\ProgramLevel;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PreSystemTuitionTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private ProgramLevel $level;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-10-07 10:00:00');

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->level = ProgramLevel::query()->where('code', 'HS2B')->firstOrFail();
        $this->level->update(['base_price_eur' => 240]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function course(string $start, string $name = 'HS2B'): Course
    {
        return Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $this->level->program_id,
            'program_level_id' => $this->level->id,
            'name' => $name,
            'start_date' => $start,
            'status' => 'active',
        ]);
    }

    private function enroll(Course $course, string $name): Enrollment
    {
        $group = Group::firstOrCreate(['course_id' => $course->id], ['campus_id' => $this->campus->id, 'name' => $course->name.'-G', 'status' => 'active', 'capacity' => 30]);
        $course->update(['managed_group_id' => $group->id]);
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => $name, 'last_name' => 'Test', 'status' => 'active']);

        return Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => now()->toDateString(), 'status' => 'active', 'progress' => 0]);
    }

    private function legacyCharge(Enrollment $enrollment, string $dueDate, string $createdAt): Charge
    {
        $charge = Charge::create([
            'campus_id' => $this->campus->id,
            'student_id' => $enrollment->student_id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $enrollment->group->course_id,
            'group_id' => $enrollment->group_id,
            'concept' => 'Mensualidad HS4A — HS4A',
            'charge_type' => 'tuition',
            'origin' => 'enrollment_auto',
            'amount' => 240,
            'currency' => 'EUR',
            'due_date' => $dueDate,
            'status' => 'overdue',
        ]);
        $charge->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $charge;
    }

    public function test_command_voids_only_unpaid_tuition_that_was_born_overdue(): void
    {
        $course = $this->course('2026-05-27');
        $preSystem = $this->legacyCharge($this->enroll($course, 'Sergio'), '2026-05-27', '2026-07-13 15:45:44');
        $paid = $this->legacyCharge($this->enroll($course, 'Carlyn'), '2026-05-27', '2026-07-13 15:45:44');
        $recent = $this->legacyCharge($this->enroll($course, 'Nuevo'), '2026-10-02', '2026-10-05 09:00:00');

        $payment = Payment::create(['campus_id' => $this->campus->id, 'student_id' => $paid->student_id, 'amount' => 240, 'currency' => 'EUR', 'paid_at' => '2026-07-20', 'method' => 'Zelle', 'status' => 'confirmed']);
        PaymentAllocation::create(['payment_id' => $payment->id, 'charge_id' => $paid->id, 'amount_applied' => 240]);

        $this->artisan('finance:void-pre-system-tuition', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Mensualidades a anular: 1')
            ->assertSuccessful();
        $this->assertNull($preSystem->fresh()->voided_at);

        $this->artisan('finance:void-pre-system-tuition')->assertSuccessful();

        $this->assertSame('Nivel cursado antes del sistema', $preSystem->fresh()->void_reason);
        $this->assertNull($paid->fresh()->voided_at, 'Con pagos no se anula.');
        $this->assertNull($recent->fresh()->voided_at, 'Las inscripciones normales no se tocan.');
    }

    public function test_ids_option_limits_the_cleanup(): void
    {
        $course = $this->course('2026-05-27');
        $keep = $this->legacyCharge($this->enroll($course, 'Ana'), '2026-05-27', '2026-07-13 15:45:44');
        $void = $this->legacyCharge($this->enroll($course, 'Luis'), '2026-05-27', '2026-07-13 15:45:44');

        $this->artisan('finance:void-pre-system-tuition', ['--ids' => (string) $void->id])->assertSuccessful();

        $this->assertNotNull($void->fresh()->voided_at);
        $this->assertNull($keep->fresh()->voided_at);
    }

    public function test_enrolling_in_a_started_course_is_due_on_enrollment_day(): void
    {
        $started = app(EnrollmentBillingService::class)->createTuitionCharge($this->enroll($this->course('2026-09-24', 'HS1A'), 'Pedro'));
        $future = app(EnrollmentBillingService::class)->createTuitionCharge($this->enroll($this->course('2026-10-20', 'HS1B'), 'María'));

        $this->assertSame('2026-10-07', $started->due_date->toDateString());
        $this->assertSame('pending', $started->fresh()->status);
        $this->assertSame('2026-10-20', $future->due_date->toDateString());
    }

    public function test_enrollment_can_skip_tuition(): void
    {
        $course = $this->course('2026-10-20');
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'name' => 'HS2B-G', 'status' => 'active', 'capacity' => 30]);
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Sin', 'last_name' => 'Cargo', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);

        $this->actingAs($admin)->get(route('enrollments.create'))->assertOk()->assertSee('No generar mensualidad');

        $this->actingAs($admin)->post(route('enrollments.store'), [
            'student_id' => $student->id,
            'group_id' => $group->id,
            'status' => 'active',
            'skip_tuition' => 1,
        ])->assertRedirect();

        $this->assertTrue(Enrollment::query()->where('student_id', $student->id)->exists());
        $this->assertFalse(Charge::query()->where('student_id', $student->id)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'enrollment.tuition_skipped')->exists());
    }

    public function test_changing_course_level_with_students_is_audited_and_warned(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Baron', 'last_name' => 'Nava', 'status' => 'active']);
        $period = Period::create(['campus_id' => $this->campus->id, 'code' => '2026-Q3', 'status' => 'active']);
        $schedule = ScheduleTemplate::create(['campus_id' => $this->campus->id, 'days' => ['mon', 'wed'], 'starts_at' => '16:00:00', 'ends_at' => '17:30:00', 'status' => 'active']);
        $course = $this->course('2026-10-20');
        $course->update(['teacher_id' => $teacher->id, 'period_id' => $period->id, 'schedule_template_id' => $schedule->id, 'academic_hours' => 30]);
        $this->enroll($course, 'Sergio');
        $nextLevel = ProgramLevel::query()->where('program_id', $this->level->program_id)->whereKeyNot($this->level->id)->firstOrFail();

        $this->actingAs($admin)->get(route('courses.edit', $course))->assertOk()->assertSee('Este curso ya tiene alumnos');

        $this->actingAs($admin)->put(route('courses.update', $course), [
            'campus_id' => $this->campus->id,
            'program_id' => $this->level->program_id,
            'program_level_id' => $nextLevel->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'schedule_template_id' => $schedule->id,
            'start_date' => '2026-10-20',
            'academic_hours' => 30,
            'status' => 'active',
        ])->assertSessionHas('warning');

        $this->assertTrue(AuditLog::query()->where('action', 'course.update')->exists());
    }
}
