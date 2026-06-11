<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Course;
use App\Models\Enrollment;
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

class CourseReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_download_course_report_pdf(): void
    {
        [$master, $course] = $this->courseFixture();

        $response = $this->actingAs($master)->get(route('courses.report.pdf', $course));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_non_master_admin_cannot_download_course_report_pdf(): void
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

        $response = $this->actingAs($admin)->get(route('courses.report.pdf', $course));

        $response->assertForbidden();
    }

    public function test_master_admin_can_download_course_report_pdf_from_any_campus(): void
    {
        [$master, $course] = $this->courseFixture();

        $otherCampus = Campus::query()->create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $master->update(['campus_id' => $otherCampus->id]);

        $response = $this->actingAs($master->fresh())->get(route('courses.report.pdf', $course));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_master_admin_sees_download_pdf_button_on_course_show(): void
    {
        [$master, $course] = $this->courseFixture();

        $response = $this->actingAs($master)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('Descargar PDF');
        $response->assertSee(route('courses.report.pdf', $course), false);
    }

    public function test_non_master_admin_does_not_see_download_pdf_button_on_course_show(): void
    {
        [, $course] = $this->courseFixture();

        $campus = Campus::query()->firstOrFail();
        $admin = User::factory()->create([
            'campus_id' => $campus->id,
            'role' => 'admin',
            'is_master' => false,
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $response = $this->actingAs($admin)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertDontSee('Descargar PDF');
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
            'name' => 'Curso reporte PDF',
            'code' => 'CRS-PDF',
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
            'last_name' => 'Report',
            'email' => 'student-report@test.dev',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $course->managed_group_id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 25,
        ]);

        return [$master, $course->fresh()];
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
