<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\MakeupRequest;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\TodayInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TodayInboxTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Campus $other;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 10:00:00');
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->other = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Alumno inscrito en un grupo con clase hoy. */
    private function classToday(Campus $campus, ?Teacher $teacher = null, string $name = 'Ana'): array
    {
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher?->id,
            'name' => 'HS5A '.$name,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $campus->id, 'course_id' => $course->id, 'teacher_id' => $teacher?->id, 'name' => 'G-'.$name, 'status' => 'active', 'capacity' => 30]);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => $name, 'last_name' => 'Test', 'status' => 'active']);
        $enrollment = Enrollment::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $session = ClassSession::create(['campus_id' => $campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-10-09', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);

        return [$student, $enrollment, $session, $course];
    }

    private function counts(User $user, ?Teacher $teacher = null): array
    {
        return collect(TodayInbox::for($user, $teacher))->pluck('count', 'key')->all();
    }

    public function test_admin_inbox_counts_respect_campus(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);

        [$student, $enrollment, $session] = $this->classToday($this->campus);
        [, $doneEnrollment, $doneSession] = $this->classToday($this->campus, null, 'Luis');
        AttendanceRecord::create(['class_session_id' => $doneSession->id, 'enrollment_id' => $doneEnrollment->id, 'status' => 'present']);
        $this->classToday($this->other, null, 'Otro');

        $old = Charge::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'concept' => 'Mensualidad agosto', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => '2026-08-01', 'status' => 'overdue']);
        Charge::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'concept' => 'Reciente', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => '2026-10-01', 'status' => 'overdue']);
        Charge::create(['campus_id' => $this->other->id, 'student_id' => $student->id, 'concept' => 'Otra sede', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => '2026-07-01', 'status' => 'overdue']);
        ChargePaymentRequest::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'charge_id' => $old->id, 'amount' => 40, 'currency' => 'USD', 'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION, 'submitted_at' => now(), 'proof_path' => 'proofs/x.pdf', 'proof_original_name' => 'x.pdf', 'proof_mime_type' => 'application/pdf', 'proof_file_size' => 100]);
        MakeupRequest::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'enrollment_id' => $enrollment->id, 'request_type' => \App\Support\MakeupRecoveryEngine::REQUEST_TYPE_MANUAL, 'price' => 10, 'currency' => 'USD', 'status' => MakeupRequest::STATUS_APPROVED_FOR_BOOKING]);
        Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Sin', 'last_name' => 'Curso', 'status' => 'active']);

        $counts = $this->counts($admin);

        $this->assertSame(1, $counts['attendance'], 'Solo la clase de su sede sin asistencia.');
        $this->assertSame(1, $counts['proofs']);
        $this->assertSame(1, $counts['makeups_book']);
        $this->assertSame(0, $counts['makeups_validate']);
        $this->assertSame(1, $counts['overdue_30'], 'Solo el vencido hace más de 30 días y de su sede.');
        $this->assertSame(1, $counts['no_enrollment']);
    }

    public function test_dashboard_shows_inbox_with_direct_links_and_without_satisfaction(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Sin', 'last_name' => 'Curso', 'status' => 'active']);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pendientes de hoy')
            ->assertSee(route('students.index', ['enrollment' => 'none']), false)
            ->assertSee(route('finance.index').'#comprobantes', false)
            ->assertDontSee('Satisfacción');

        $this->actingAs($admin)->get(route('students.index', ['enrollment' => 'none']))
            ->assertOk()
            ->assertSee('Mostrando alumnos activos sin inscripción')
            ->assertSee('Sin Curso');
    }

    public function test_teacher_inbox_only_counts_own_classes(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]);
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Sofia', 'last_name' => 'Baron', 'user_id' => $user->id, 'status' => 'active']);
        [, , $mine] = $this->classToday($this->campus, $teacher, 'Mia');
        $this->classToday($this->campus, null, 'Ajena');

        $this->assertSame(['attendance' => 1], $this->counts($user, $teacher));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Clases de hoy sin asistencia')
            ->assertDontSee('Comprobantes del portal')
            ->assertSee(route('attendance.index', ['class_session_id' => $mine->id]), false);
    }
}
