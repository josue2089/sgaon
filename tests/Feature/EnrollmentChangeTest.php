<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\User;
use App\Support\FinanceReconcile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnrollmentChangeTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private Student $student;

    private Group $group;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00', config('app.timezone')));
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'status' => 'active']);
        $this->group = $this->group('HS3A');
        $this->enrollment = Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'group_id' => $this->group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 10]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function group(string $name, ?Campus $campus = null, int $capacity = 20): Group
    {
        $campus ??= $this->campus;
        $level = ProgramLevel::query()->where('code', 'HS3A')->firstOrFail();
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => 'Curso '.$name,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);

        return Group::create(['campus_id' => $campus->id, 'course_id' => $course->id, 'name' => $name, 'status' => 'active', 'capacity' => $capacity]);
    }

    private function charge(string $concept, string $due, array $extra = []): Charge
    {
        return Charge::create($extra + [
            'campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'enrollment_id' => $this->enrollment->id,
            'course_id' => $this->group->course_id, 'group_id' => $this->group->id, 'concept' => $concept, 'charge_type' => 'tuition',
            'amount' => 40, 'currency' => 'USD', 'due_date' => $due, 'status' => 'pending',
        ]);
    }

    private function pay(Charge $charge, float $amount): void
    {
        Payment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'charge_id' => $charge->id, 'amount' => $amount, 'currency' => 'USD', 'paid_at' => '2026-10-01', 'status' => 'confirmed']);
        FinanceReconcile::syncCharge($charge->fresh());
    }

    private function withdraw(array $overrides = [])
    {
        return $this->actingAs($this->admin)->post(route('enrollments.withdraw', $this->enrollment), $overrides + [
            'withdrawn_at' => '2026-11-05', 'reason' => 'Se muda', 'void_future_charges' => 1,
        ]);
    }

    public function test_withdraw_voids_only_future_unpaid_charges(): void
    {
        $past = $this->charge('Vencido', '2026-10-05');
        $today = $this->charge('Vence hoy', '2026-11-05');
        $future = $this->charge('Futuro', '2026-12-05');
        $paid = $this->charge('Futuro pagado', '2026-12-10');
        $this->pay($paid, 40);
        $partial = $this->charge('Futuro abonado', '2026-12-15');
        $this->pay($partial, 10);

        $this->withdraw()->assertSessionHasNoErrors()->assertSessionHas('success', fn (string $message) => str_contains($message, 'Cargos futuros anulados: 1.'));

        $this->assertNotNull($future->fresh()->voided_at);
        $this->assertNull($past->fresh()->voided_at);
        $this->assertNull($today->fresh()->voided_at);
        $this->assertNull($paid->fresh()->voided_at);
        $this->assertNull($partial->fresh()->voided_at);

        $enrollment = $this->enrollment->fresh();
        $this->assertSame('withdrawn', $enrollment->status);
        $this->assertStringContainsString('Retirado el 05/11/2026: Se muda', $enrollment->notes);
        $this->assertSame((int) $this->admin->id, (int) $future->fresh()->voided_by);
        $this->assertSame(1, AuditLog::query()->where('action', 'enrollment.withdraw')->sole()->payload['voided_charges']);
    }

    public function test_withdraw_without_the_option_keeps_charges_and_requires_a_reason(): void
    {
        $future = $this->charge('Futuro', '2026-12-05');

        $this->withdraw(['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('active', $this->enrollment->fresh()->status);

        $this->withdraw(['void_future_charges' => 0])->assertSessionHasNoErrors();
        $this->assertNull($future->fresh()->voided_at);
        $this->assertSame('withdrawn', $this->enrollment->fresh()->status);

        $this->withdraw()->assertSessionHasErrors('enrollment');
    }

    public function test_transfer_moving_charges_leaves_one_active_enrollment_and_no_duplicates(): void
    {
        $target = $this->group('HS3A-Tarde');
        $unpaid = $this->charge('Mensualidad', '2026-10-05');
        $paid = $this->charge('Pagada', '2026-09-05');
        $this->pay($paid, 40);
        $makeup = $this->charge('Recuperativa', '2026-10-10', ['charge_type' => 'makeup']);

        $this->actingAs($this->admin)->get(route('enrollments.transfer.edit', $this->enrollment))->assertOk()->assertSee('Curso HS3A-Tarde')->assertSee('Mensualidad');

        $this->actingAs($this->admin)->post(route('enrollments.transfer', $this->enrollment), [
            'group_id' => $target->id, 'changed_at' => '2026-11-05', 'reason' => 'Cambio de horario', 'charges' => 'move',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('withdrawn', $this->enrollment->fresh()->status);
        $new = Enrollment::query()->where('group_id', $target->id)->sole();
        $this->assertSame('active', $new->status);
        $this->assertSame(1, Enrollment::query()->where('student_id', $this->student->id)->where('status', 'active')->count());

        foreach ([$unpaid, $paid] as $charge) {
            $this->assertSame($new->id, $charge->fresh()->enrollment_id);
            $this->assertSame($target->id, $charge->fresh()->group_id);
            $this->assertNull($charge->fresh()->voided_at);
        }
        $this->assertSame($this->enrollment->id, $makeup->fresh()->enrollment_id);
        $this->assertSame(3, Charge::query()->where('student_id', $this->student->id)->count(), 'No genera mensualidad nueva.');
        $this->assertTrue(AuditLog::query()->where('action', 'enrollment.transfer')->exists());
    }

    public function test_transfer_voiding_charges_creates_the_new_tuition(): void
    {
        $target = $this->group('HS3A-Tarde');
        \App\Models\CampusProgramPrice::create(['campus_id' => $this->campus->id, 'program_id' => $target->course->program_id, 'amount' => 40, 'currency' => 'USD']);
        $unpaid = $this->charge('Mensualidad', '2026-10-05');
        $partial = $this->charge('Abonada', '2026-10-06');
        $this->pay($partial, 10);

        $this->actingAs($this->admin)->post(route('enrollments.transfer', $this->enrollment), [
            'group_id' => $target->id, 'changed_at' => '2026-11-05', 'reason' => 'Otro nivel', 'charges' => 'void',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($unpaid->fresh()->voided_at);
        $this->assertNull($partial->fresh()->voided_at);
        $new = Enrollment::query()->where('group_id', $target->id)->sole();
        $this->assertSame(1, Charge::query()->where('enrollment_id', $new->id)->where('origin', 'enrollment_auto')->count());
    }

    public function test_transfer_rejects_invalid_targets(): void
    {
        $full = $this->group('Lleno', null, 1);
        Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Otro', 'last_name' => 'Alumno', 'status' => 'active'])->id, 'group_id' => $full->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $inactive = $this->group('Cerrado');
        $inactive->update(['status' => 'inactive']);
        $payload = fn (Group $group) => ['group_id' => $group->id, 'changed_at' => '2026-11-05', 'reason' => 'x', 'charges' => 'move'];

        foreach ([$this->group, $full, $inactive] as $group) {
            $this->actingAs($this->admin)->post(route('enrollments.transfer', $this->enrollment), $payload($group))->assertSessionHasErrors('group_id');
        }
        $this->assertSame('active', $this->enrollment->fresh()->status);
        $this->assertSame(0, Enrollment::query()->whereIn('group_id', [$full->id, $inactive->id])->where('student_id', $this->student->id)->count());

        $otherCampus = Campus::create(['name' => 'Otra', 'code' => 'OTR', 'status' => 'active']);
        $local = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $this->actingAs($local)->post(route('enrollments.transfer', $this->enrollment), $payload($this->group('Fuera', $otherCampus)))->assertForbidden();
    }

    public function test_admin_from_another_campus_cannot_withdraw(): void
    {
        $otherCampus = Campus::create(['name' => 'Otra', 'code' => 'OTR', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'admin', 'campus_id' => $otherCampus->id, 'is_master' => false]);

        $this->actingAs($outsider)->post(route('enrollments.withdraw', $this->enrollment), ['withdrawn_at' => '2026-11-05', 'reason' => 'x'])->assertForbidden();
        $this->assertSame('active', $this->enrollment->fresh()->status);
    }

    public function test_editing_cannot_change_student_group_or_withdraw(): void
    {
        $other = $this->group('Otro');
        $otherStudent = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Luis', 'last_name' => 'Rey', 'status' => 'active']);

        $this->actingAs($this->admin)->get(route('enrollments.edit', $this->enrollment))
            ->assertOk()
            ->assertSee('Ana Gil')
            ->assertDontSee('name="group_id"', false)
            ->assertDontSee('value="withdrawn"', false);

        $this->actingAs($this->admin)->put(route('enrollments.update', $this->enrollment), [
            'student_id' => $otherStudent->id, 'group_id' => $other->id, 'status' => 'active', 'progress' => 50,
        ])->assertSessionHasNoErrors();

        $fresh = $this->enrollment->fresh();
        $this->assertSame($this->student->id, $fresh->student_id);
        $this->assertSame($this->group->id, $fresh->group_id);
        $this->assertSame(50.0, (float) $fresh->progress);

        $this->actingAs($this->admin)->put(route('enrollments.update', $this->enrollment), ['status' => 'withdrawn'])->assertSessionHasErrors('status');
    }

    public function test_actions_appear_on_course_student_and_enrollment_screens(): void
    {
        $this->charge('Mensualidad', '2026-10-05');
        $session = ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $this->group->id, 'sequence' => 1, 'session_date' => '2026-10-01', 'starts_at' => '16:00:00', 'ends_at' => '17:00:00']);
        AttendanceRecord::create(['class_session_id' => $session->id, 'enrollment_id' => $this->enrollment->id, 'status' => 'present']);
        $course = $this->group->course;
        $course->forceFill(['managed_group_id' => $this->group->id])->save();

        foreach ([route('courses.show', $course), route('students.show', $this->student), route('enrollments.index')] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('Cambiar de curso')->assertSee('withdraw-enrollment-'.$this->enrollment->id, false);
        }
        $this->actingAs($this->admin)->get(route('courses.show', $course))->assertDontSee('>Quitar<', false);
    }
}
