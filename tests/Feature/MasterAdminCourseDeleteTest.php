<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
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
use App\Models\User;
use App\Support\CoursePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAdminCourseDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_delete_course_and_related_group_data(): void
    {
        [$master, $course, $student] = $this->courseFixture();

        $response = $this->actingAs($master)->delete(route('courses.destroy', $course));

        $response->assertRedirect(route('courses.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('groups', ['course_id' => $course->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_non_master_admin_cannot_delete_course(): void
    {
        [$master, $course] = $this->courseFixture();

        $campus = Campus::query()->firstOrFail();
        $admin = User::factory()->create([
            'campus_id' => $campus->id,
            'role' => 'admin',
            'is_master' => false,
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $response = $this->actingAs($admin)->delete(route('courses.destroy', $course));

        $response->assertForbidden();
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_master_admin_sees_delete_modal_on_course_index(): void
    {
        $master = $this->createMasterAdmin();

        $response = $this->actingAs($master)->get(route('courses.index'));

        $response->assertOk();
        $response->assertSee('Eliminar');
        $response->assertSee('Eliminar permanentemente');
        $response->assertSee('Eliminar curso');
        $response->assertSee('se eliminan del sistema');
    }

    private function courseFixture(): array
    {
        $master = $this->createMasterAdmin();
        $campus = Campus::query()->firstOrFail();
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
            'days' => ['mon', 'wed'],
            'starts_at' => '16:00:00',
            'ends_at' => '17:00:00',
            'status' => 'active',
        ]);

        $course = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $program->id,
            'program_level_id' => $programLevel->id,
            'period_id' => $period->id,
            'schedule_template_id' => $schedule->id,
            'name' => 'Curso a eliminar',
            'start_date' => now()->subWeek()->toDateString(),
            'academic_hours' => 40,
            'status' => 'active',
        ]);
        $course->load(['period', 'scheduleTemplate', 'programLevel']);
        CoursePlanner::sync($course);
        $course->refresh();

        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Student',
            'last_name' => 'Course',
            'email' => 'student-course@test.dev',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $course->managed_group_id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);

        ClassSession::query()->where('group_id', $course->managed_group_id)->limit(1)->get();

        return [$master, $course->fresh(), $student];
    }

    private function createMasterAdmin(): User
    {
        $campus = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $master = User::factory()->create([
            'campus_id' => $campus->id,
            'role' => 'admin',
            'is_master' => true,
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $master->roles()->syncWithoutDetaching([$adminRole->id]);

        return $master;
    }
}
