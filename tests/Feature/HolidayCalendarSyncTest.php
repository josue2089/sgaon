<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Period;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Role;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CoursePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_holiday_moves_session_off_that_date_and_extends_end_date(): void
    {
        [$admin, $course] = $this->fridayCourse();
        $originalEnd = $course->end_date->toDateString();
        $count = $this->sessionCount($course);

        $this->actingAs($admin)
            ->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'))
            ->assertRedirect(route('holidays.index'))
            ->assertSessionHas('success');

        $course->refresh();
        $this->assertFalse($this->hasSessionOn($course, '2026-06-19'));
        $this->assertSame($count, $this->sessionCount($course));
        $this->assertSame('2026-07-31', $originalEnd);
        $this->assertSame('2026-08-07', $course->end_date->toDateString());
    }

    public function test_session_with_attendance_on_holiday_is_kept_and_reported(): void
    {
        [$admin, $course, $enrollment] = $this->fridayCourse();
        $count = $this->sessionCount($course);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();
        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $this->actingAs($admin)
            ->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'))
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, '19/06/2026'));

        $this->assertTrue($this->hasSessionOn($course, '2026-06-19'));
        $this->assertSame(1, AttendanceRecord::query()->where('class_session_id', $session->id)->count());
        $this->assertSame($count + 1, $this->sessionCount($course));

        $this->actingAs($admin)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Feriado')
            ->assertSee('Recalcular calendario')
            ->assertSee('Editar fecha');
    }

    public function test_deleting_holiday_resyncs_courses(): void
    {
        [$admin, $course] = $this->fridayCourse();
        $this->actingAs($admin)->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'));
        $this->assertFalse($this->hasSessionOn($course, '2026-06-19'));

        $holiday = Holiday::query()->firstOrFail();
        $this->actingAs($admin)->delete(route('holidays.destroy', $holiday))->assertRedirect();

        $this->assertTrue($this->hasSessionOn($course, '2026-06-19'));
        $this->assertSame('2026-07-31', $course->fresh()->end_date->toDateString());
    }

    public function test_recalculate_button_applies_existing_holidays(): void
    {
        [$admin, $course] = $this->fridayCourse();
        Holiday::query()->create([
            'campus_id' => $course->campus_id,
            'name' => 'Vacaciones',
            'holiday_date' => '2026-06-26',
            'is_recurring' => false,
            'status' => 'active',
        ]);
        $this->assertTrue($this->hasSessionOn($course, '2026-06-26'));

        $this->actingAs($admin)
            ->post(route('courses.recalculate-calendar', $course))
            ->assertRedirect(route('courses.show', $course))
            ->assertSessionHas('success');

        $this->assertFalse($this->hasSessionOn($course, '2026-06-26'));
    }

    public function test_teacher_topic_is_preserved_on_resync(): void
    {
        [$admin, $course] = $this->fridayCourse();
        ClassSession::query()
            ->where('group_id', $course->managed_group_id)
            ->whereDate('session_date', '2026-06-12')
            ->update(['topic' => 'Unit 2 vocabulary', 'program_status' => 'on_track']);

        $this->actingAs($admin)->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'));

        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-12')->firstOrFail();
        $this->assertSame('Unit 2 vocabulary', $session->topic);
        $this->assertSame('on_track', $session->program_status);
    }

    public function test_session_can_be_moved_after_group_end_date_and_end_date_updates(): void
    {
        [$admin, $course] = $this->fridayCourse();
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('sessions.update', $session), $this->sessionPayload($session, '2026-08-14') + ['redirect_to' => 'course'])
            ->assertRedirect(route('courses.show', $course))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-14', $session->fresh()->session_date->toDateString());
        $this->assertSame('2026-08-14', $course->fresh()->end_date->toDateString());
        $this->assertSame('2026-08-14', $course->fresh()->managedGroup->end_date->toDateString());
    }

    public function test_session_cannot_be_moved_to_a_holiday(): void
    {
        [$admin, $course] = $this->fridayCourse();
        Holiday::query()->create([
            'campus_id' => null,
            'name' => 'Día patrio',
            'holiday_date' => '2026-08-14',
            'is_recurring' => false,
            'status' => 'active',
        ]);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('sessions.update', $session), $this->sessionPayload($session, '2026-08-14'))
            ->assertSessionHasErrors('session_date');

        $this->assertSame('2026-06-05', $session->fresh()->session_date->toDateString());
    }

    private function holidayPayload(Course $course, string $date): array
    {
        return [
            'campus_id' => $course->campus_id,
            'name' => 'Vacaciones',
            'is_recurring' => 0,
            'holiday_date' => $date,
            'status' => 'active',
        ];
    }

    private function sessionPayload(ClassSession $session, string $date): array
    {
        return [
            'group_id' => $session->group_id,
            'session_date' => $date,
            'starts_at' => substr((string) $session->starts_at, 0, 5),
            'ends_at' => substr((string) $session->ends_at, 0, 5),
        ];
    }

    private function sessionCount(Course $course): int
    {
        return ClassSession::query()->where('group_id', $course->managed_group_id)->count();
    }

    private function hasSessionOn(Course $course, string $date): bool
    {
        return ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', $date)->exists();
    }

    /**
     * Curso de viernes que arranca el 05/06/2026: 9 sesiones, termina el 31/07/2026.
     *
     * @return array{0: User, 1: Course, 2: Enrollment}
     */
    private function fridayCourse(): array
    {
        $campus = Campus::query()->create(['name' => 'La Cascada', 'code' => 'CAS', 'status' => 'active']);
        $admin = User::factory()->create(['campus_id' => $campus->id, 'role' => 'admin', 'is_master' => true]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $teacher = Teacher::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Teacher',
            'last_name' => 'Holiday',
            'email' => 'teacher-holiday@test.dev',
            'status' => 'active',
        ]);

        $program = Program::query()->firstOrFail();
        $programLevel = ProgramLevel::query()->where('program_id', $program->id)->orderBy('sort_order')->firstOrFail();
        $academicLevel = AcademicLevel::query()->create(['campus_id' => $campus->id, 'name' => 'HS']);
        $period = Period::query()->create([
            'campus_id' => $campus->id,
            'code' => '2026-Q2',
            'description' => 'Q2',
            'status' => 'active',
        ]);
        $schedule = ScheduleTemplate::query()->create([
            'campus_id' => $campus->id,
            'days' => ['fri'],
            'starts_at' => '14:20:00',
            'ends_at' => '17:40:00',
            'status' => 'active',
        ]);

        $course = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $program->id,
            'program_level_id' => $programLevel->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'schedule_template_id' => $schedule->id,
            'name' => 'HS Friday',
            'start_date' => '2026-06-05',
            'academic_hours' => 40,
            'status' => 'active',
        ]);
        $course->load(['teacher', 'period', 'scheduleTemplate', 'programLevel']);
        CoursePlanner::sync($course);

        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Student',
            'last_name' => 'Holiday',
            'status' => 'active',
        ]);
        $enrollment = Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $course->fresh()->managed_group_id,
            'enrolled_at' => '2026-06-05',
            'status' => 'active',
            'progress' => 0,
        ]);

        return [$admin, $course->fresh(), $enrollment];
    }
}
