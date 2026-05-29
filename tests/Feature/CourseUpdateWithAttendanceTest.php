<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
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

class CourseUpdateWithAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_can_update_teacher_and_status_when_sessions_have_attendance(): void
    {
        [$admin, $course, $teacherB, $enrollment] = $this->courseWithAttendance();

        $payload = $this->coursePayload($course, [
            'teacher_id' => $teacherB->id,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($admin)->put(route('courses.update', $course), $payload);

        $response->assertRedirect(route('courses.show', $course));
        $response->assertSessionHas('success');

        $course->refresh()->load('managedGroup');
        $this->assertSame((int) $teacherB->id, (int) $course->teacher_id);
        $this->assertSame('inactive', $course->status);
        $this->assertSame((int) $teacherB->id, (int) $course->managedGroup->teacher_id);
        $this->assertSame('inactive', $course->managedGroup->status);
        $this->assertSame(1, ClassSession::query()->where('group_id', $course->managed_group_id)->count());
        $this->assertSame(1, AttendanceRecord::query()->where('enrollment_id', $enrollment->id)->count());
    }

    public function test_course_cannot_change_calendar_when_sessions_have_attendance(): void
    {
        [$admin, $course] = $this->courseWithAttendance();

        $payload = $this->coursePayload($course, [
            'start_date' => $course->start_date->copy()->addWeek()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->put(route('courses.update', $course), $payload);

        $response->assertSessionHasErrors('start_date');
    }

    private function courseWithAttendance(): array
    {
        $campus = Campus::query()->create(['name' => 'Campus A', 'code' => 'CA', 'status' => 'active']);
        $admin = User::factory()->create(['campus_id' => $campus->id, 'role' => 'admin']);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $teacherA = Teacher::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Teacher',
            'last_name' => 'Alpha',
            'email' => 'teacher-a@test.dev',
            'status' => 'active',
        ]);
        $teacherB = Teacher::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Teacher',
            'last_name' => 'Beta',
            'email' => 'teacher-b@test.dev',
            'status' => 'active',
        ]);

        $program = Program::query()->firstOrFail();
        $programLevel = ProgramLevel::query()->where('program_id', $program->id)->orderBy('sort_order')->firstOrFail();
        $academicLevel = AcademicLevel::query()->create(['campus_id' => $campus->id, 'name' => 'Primary']);
        $period = Period::query()->create([
            'campus_id' => $campus->id,
            'code' => '2026-Q1',
            'description' => 'Periodo Q1',
            'status' => 'active',
        ]);
        $schedule = ScheduleTemplate::query()->create([
            'campus_id' => $campus->id,
            'days' => ['mon', 'wed', 'fri'],
            'starts_at' => '16:00:00',
            'ends_at' => '17:00:00',
            'status' => 'active',
        ]);

        $course = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $program->id,
            'program_level_id' => $programLevel->id,
            'teacher_id' => $teacherA->id,
            'period_id' => $period->id,
            'schedule_template_id' => $schedule->id,
            'name' => 'Curso con asistencia',
            'start_date' => now()->subWeeks(2)->toDateString(),
            'academic_hours' => 40,
            'status' => 'active',
        ]);
        $course->load(['teacher', 'period', 'scheduleTemplate', 'programLevel']);
        CoursePlanner::sync($course);

        $course->refresh()->load('managedGroup');
        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => 'student-one@test.dev',
            'status' => 'active',
        ]);
        $enrollment = Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $course->managedGroup->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);

        $session = ClassSession::query()->where('group_id', $course->managedGroup->id)->orderBy('session_date')->firstOrFail();
        ClassSession::query()->where('group_id', $course->managedGroup->id)->where('id', '!=', $session->id)->delete();

        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        return [$admin, $course->fresh(['teacher', 'period', 'scheduleTemplate', 'programLevel']), $teacherB, $enrollment];
    }

    private function coursePayload(Course $course, array $overrides = []): array
    {
        return array_merge([
            'campus_id' => $course->campus_id,
            'program_id' => $course->program_id,
            'program_level_id' => $course->program_level_id,
            'teacher_id' => $course->teacher_id,
            'period_id' => $course->period_id,
            'schedule_template_id' => $course->schedule_template_id,
            'name' => $course->name,
            'code' => $course->code,
            'description' => $course->description,
            'start_date' => $course->start_date->toDateString(),
            'academic_hours' => $course->academic_hours,
            'status' => $course->status,
        ], $overrides);
    }
}
