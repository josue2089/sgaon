<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeacherMultiCampusTest extends TestCase
{
    use RefreshDatabase;

    private Campus $picacho;

    private Campus $cascada;

    private User $user;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-10-17 09:00:00');
        $this->picacho = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->cascada = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        // Usuario de Picacho que también dicta en Cascada (caso Rachev / Flores Landaeta).
        $this->user = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->picacho->id]);
        $this->teacher = Teacher::create(['campus_id' => $this->picacho->id, 'first_name' => 'Filip', 'last_name' => 'Rachev', 'user_id' => $this->user->id, 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function classToday(Campus $campus, ?Teacher $teacher, string $name): array
    {
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher?->id,
            'name' => $name,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $campus->id, 'course_id' => $course->id, 'teacher_id' => $teacher?->id, 'name' => $name.'-G', 'status' => 'active', 'capacity' => 20]);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Alumno', 'last_name' => $name, 'status' => 'active']);
        $enrollment = Enrollment::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'status' => 'active', 'progress' => 0]);
        $session = ClassSession::create(['campus_id' => $campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-10-17', 'starts_at' => '08:00:00', 'ends_at' => '11:30:00']);

        return [$course, $session, $enrollment];
    }

    public function test_teacher_sees_and_records_attendance_in_another_campus(): void
    {
        [, $picachoSession] = $this->classToday($this->picacho, $this->teacher, 'HS4A Picacho');
        [$cascadaCourse, $cascadaSession, $cascadaEnrollment] = $this->classToday($this->cascada, $this->teacher, 'HS4A Sabado Cascada');
        [, $otherSession] = $this->classToday($this->cascada, null, 'HS2B Ajeno');

        $this->actingAs($this->user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('value="'.$picachoSession->id.'"', false)
            ->assertSee('value="'.$cascadaSession->id.'"', false)
            ->assertSee('HS4A Sabado Cascada')
            ->assertDontSee('value="'.$otherSession->id.'"', false);

        $this->actingAs($this->user)->get(route('attendance.index', ['class_session_id' => $cascadaSession->id]))->assertOk();

        $this->actingAs($this->user)->post(route('attendance.store'), [
            'class_session_id' => $cascadaSession->id,
            'records' => [['enrollment_id' => $cascadaEnrollment->id, 'status' => 'present', 'notes' => null]],
        ])->assertSessionHas('success');
        $this->assertTrue(AttendanceRecord::query()->where('class_session_id', $cascadaSession->id)->exists());

        $this->actingAs($this->user)->get(route('attendance.index', ['class_session_id' => $otherSession->id]))
            ->assertOk()
            ->assertDontSee('Alumno HS2B Ajeno');

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('HS4A Sabado Cascada');

        $this->actingAs($this->user)->get(route('courses.grades.index', $cascadaCourse))->assertOk();
    }

    public function test_site_admin_scope_is_unchanged(): void
    {
        [, $cascadaSession] = $this->classToday($this->cascada, $this->teacher, 'HS4A Sabado Cascada');
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->picacho->id, 'is_master' => false]);

        $this->actingAs($admin)->get(route('attendance.index'))
            ->assertOk()
            ->assertDontSee('value="'.$cascadaSession->id.'"', false);
    }
}
