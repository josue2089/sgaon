<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Holiday;
use App\Models\Period;
use App\Models\ProgramLevel;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CoursePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HeldOnHolidayTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;

    private Course $course;

    private ClassSession $session;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-10-15 10:00:00');

        // Caso HS3A V 2.30 5.40 (Cascada): feriado global el viernes 05/06/2026, pero la clase se dio.
        $campus = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        Holiday::create(['campus_id' => null, 'name' => 'Emergencia Nacional', 'kind' => 'holiday', 'holiday_date' => '2026-06-05', 'status' => 'active']);
        $this->teacherUser = User::factory()->create(['role' => 'teacher', 'campus_id' => $campus->id]);
        $teacher = Teacher::create(['campus_id' => $campus->id, 'first_name' => 'Silvestre', 'last_name' => 'Arismendi', 'user_id' => $this->teacherUser->id, 'status' => 'active']);
        $level = ProgramLevel::query()->firstOrFail();
        $this->course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher->id,
            'period_id' => Period::create(['campus_id' => $campus->id, 'code' => '2026-Q2', 'status' => 'active'])->id,
            'schedule_template_id' => ScheduleTemplate::create(['campus_id' => $campus->id, 'days' => ['fri'], 'starts_at' => '14:30:00', 'ends_at' => '17:40:00', 'status' => 'active'])->id,
            'academic_hours' => 30,
            'name' => 'HS3A - Horario: V 2.30 5.40',
            'start_date' => '2026-05-29',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $campus->id, 'course_id' => $this->course->id, 'teacher_id' => $teacher->id, 'name' => 'HS3A', 'status' => 'active', 'capacity' => 20, 'start_date' => '2026-05-29']);
        $this->course->update(['managed_group_id' => $group->id]);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'status' => 'active']);
        $this->enrollment = Enrollment::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'status' => 'active', 'progress' => 0]);
        // El calendario saltó el feriado: la clase quedó el 12/06.
        $this->session = ClassSession::create(['campus_id' => $campus->id, 'group_id' => $group->id, 'sequence' => 2, 'session_date' => '2026-06-12', 'starts_at' => '14:30:00', 'ends_at' => '17:40:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_holiday_still_blocks_without_the_checkbox(): void
    {
        $this->actingAs($this->teacherUser)
            ->post(route('attendance.reschedule'), ['class_session_id' => $this->session->id, 'session_date' => '2026-06-05'])
            ->assertSessionHasErrors('session_date');

        $this->assertSame('2026-06-12', $this->session->fresh()->session_date->toDateString());
    }

    public function test_class_held_on_holiday_can_be_moved_and_recorded(): void
    {
        $this->actingAs($this->teacherUser)
            ->post(route('attendance.reschedule'), ['class_session_id' => $this->session->id, 'session_date' => '2026-06-05', 'held_on_holiday' => 1])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $session = $this->session->fresh();
        $this->assertSame('2026-06-05', $session->session_date->toDateString());
        $this->assertTrue($session->held_on_holiday);
        $this->assertTrue($session->date_locked);
        $this->assertSame('Emergencia Nacional', AuditLog::query()->where('action', 'session.reschedule')->latest('id')->first()->payload['held_on_holiday'] ?? null);

        $this->actingAs($this->teacherUser)->get(route('attendance.index', ['class_session_id' => $session->id]))
            ->assertOk()
            ->assertSee('Se dio en feriado (Emergencia Nacional)')
            ->assertDontSee('Esta clase cae en feriado');

        $this->actingAs($this->teacherUser)->post(route('attendance.store'), [
            'class_session_id' => $session->id,
            'records' => [['enrollment_id' => $this->enrollment->id, 'status' => 'present', 'notes' => null]],
        ])->assertSessionHas('success');
        $this->assertTrue(AttendanceRecord::query()->where('class_session_id', $session->id)->exists());

        // Recalcular el calendario no vuelve a sacar la clase del feriado.
        CoursePlanner::sync($this->course->fresh(['teacher', 'period', 'scheduleTemplate', 'managedGroup.sessions.attendanceRecords']), true);
        $this->assertSame('2026-06-05', $session->fresh()->session_date->toDateString());
    }
}
