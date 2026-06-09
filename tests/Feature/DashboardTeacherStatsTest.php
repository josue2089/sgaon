<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTeacherStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UatFixtureSeeder::class);
    }

    public function test_teacher_dashboard_counts_only_students_in_their_courses(): void
    {
        $campus = Campus::query()->where('code', 'PIC-UAT')->firstOrFail();
        $teacherUser = User::where('email', 'teachera@uat.test')->firstOrFail();

        foreach (range(1, 5) as $i) {
            Student::query()->create([
                'campus_id' => $campus->id,
                'first_name' => "Extra{$i}",
                'last_name' => 'Student',
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($teacherUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('studentsCount', 1);
        $response->assertViewHas('coursesCount', 1);
        $response->assertSee('En tus cursos');
    }

    public function test_teacher_dashboard_excludes_students_from_other_teachers_groups(): void
    {
        $campus = Campus::query()->where('code', 'PIC-UAT')->firstOrFail();
        $teacherUser = User::where('email', 'teachera@uat.test')->firstOrFail();
        $otherTeacher = Teacher::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Other',
            'last_name' => 'Teacher',
            'email' => 'otherteacher@test.dev',
            'status' => 'active',
        ]);

        $otherStudent = Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Other',
            'last_name' => 'Student',
            'status' => 'active',
        ]);

        $level = AcademicLevel::query()->where('campus_id', $campus->id)->firstOrFail();
        $otherCourse = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $level->id,
            'name' => 'Other Course',
            'teacher_id' => $otherTeacher->id,
            'status' => 'active',
        ]);

        $otherGroup = Group::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $otherCourse->id,
            'teacher_id' => $otherTeacher->id,
            'name' => 'G-Other',
            'period' => '2026',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $otherStudent->id,
            'group_id' => $otherGroup->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);

        $response = $this->actingAs($teacherUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('studentsCount', 1);
    }
}
