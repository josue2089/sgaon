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

class AttendanceMobileTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $teacherUser;

    private ClassSession $session;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-10-13 15:00:00');

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->teacherUser = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]);
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Sofia', 'last_name' => 'Baron', 'user_id' => $this->teacherUser->id, 'status' => 'active']);

        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher->id,
            'name' => 'HS2B Lunes',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id, 'name' => 'HS2B', 'status' => 'active', 'capacity' => 20]);
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Mateo', 'last_name' => 'Díaz', 'status' => 'active']);
        $this->enrollment = Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'status' => 'active', 'progress' => 0]);
        $this->session = ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-10-13', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_teacher_opens_on_today_classes_cards(): void
    {
        $this->actingAs($this->teacherUser)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Mis clases de hoy')
            ->assertSee('HS2B Lunes')
            ->assertSee(route('attendance.index', ['class_session_id' => $this->session->id]), false)
            ->assertSee('Pendiente')
            ->assertDontSee('attendance-metrics', false);

        AttendanceRecord::create(['class_session_id' => $this->session->id, 'enrollment_id' => $this->enrollment->id, 'status' => 'present']);

        $this->actingAs($this->teacherUser)->get(route('attendance.index'))->assertSee('Tomada');
    }

    public function test_session_page_has_big_toggles_save_bar_and_reschedule_modal(): void
    {
        $this->actingAs($this->teacherUser)->get(route('attendance.index', ['class_session_id' => $this->session->id]))
            ->assertOk()
            ->assertSee('attendance-savebar', false)
            ->assertSee('data-status-value="absent"', false)
            ->assertSee('id="reschedule-modal"', false)
            ->assertSee('Cambiar fecha de la clase')
            ->assertDontSee('<h2 class="section-title">Fecha de la clase</h2>', false);
    }

    public function test_teacher_saves_attendance_and_admin_gets_makeup_link_for_absence(): void
    {
        $this->actingAs($this->teacherUser)->post(route('attendance.store'), [
            'class_session_id' => $this->session->id,
            'records' => [['enrollment_id' => $this->enrollment->id, 'status' => 'absent', 'notes' => null]],
        ])->assertSessionHas('success');

        $this->assertSame('absent', AttendanceRecord::query()->sole()->status);

        $this->actingAs($this->teacherUser)->get(route('attendance.index', ['class_session_id' => $this->session->id]))
            ->assertDontSee('Registrar recuperativa');

        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $this->actingAs($admin)->get(route('attendance.index', ['class_session_id' => $this->session->id]))
            ->assertSee('Registrar recuperativa')
            ->assertSee(route('students.makeups.create', $this->enrollment->student_id), false);

        $this->actingAs($admin)->get(route('attendance.index'))
            ->assertSee('Clases de hoy')
            ->assertSee('Tomada');
    }
}
