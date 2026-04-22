<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\GradeEvaluationSet;
use App\Models\Group;
use App\Models\Permission;
use App\Models\Period;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Role;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelRenewalAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_next_course_draft_without_teacher_when_course_is_due(): void
    {
        $campus = Campus::query()->create(['name' => 'Campus A', 'code' => 'CA', 'status' => 'active']);
        $studentUser = User::factory()->create(['campus_id' => $campus->id, 'role' => 'student', 'email' => 'student@renewal.test']);
        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'user_id' => $studentUser->id,
            'first_name' => 'Student',
            'last_name' => 'Renewal',
            'email' => $studentUser->email,
            'status' => 'active',
        ]);

        $program = Program::query()->firstOrFail();
        $period = Period::query()->create([
            'campus_id' => $campus->id,
            'code' => '2026-Q1',
            'description' => 'Periodo Q1',
            'status' => 'active',
        ]);
        $level1 = ProgramLevel::query()->where('program_id', $program->id)->orderBy('sort_order')->firstOrFail();
        $level2 = ProgramLevel::query()->where('program_id', $program->id)->where('sort_order', '>', $level1->sort_order)->orderBy('sort_order')->firstOrFail();
        $academicLevel = AcademicLevel::query()->create(['campus_id' => $campus->id, 'name' => 'Primary']);
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
            'program_level_id' => $level1->id,
            'schedule_template_id' => $schedule->id,
            'name' => 'Nivel actual',
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'academic_hours' => 40,
            'status' => 'active',
        ]);
        $group = Group::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $course->id,
            'name' => 'GA',
            'period' => '2026',
            'schedule' => $schedule->display_label,
            'status' => 'active',
        ]);
        $course->update(['managed_group_id' => $group->id]);

        $enrollment = Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);

        $set = GradeEvaluationSet::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'evaluated_on' => now()->toDateString(),
            'title' => 'Final',
            'created_by' => $studentUser->id,
        ]);
        GradeEntry::query()->create([
            'grade_evaluation_set_id' => $set->id,
            'campus_id' => $campus->id,
            'enrollment_id' => $enrollment->id,
            'vocabulary_rating' => 'acceptable',
            'listening_rating' => 'regular',
            'speaking_rating' => 'acceptable',
            'writing_rating' => 'outstanding',
            'grammar_rating' => 'regular',
            'observations' => null,
        ]);

        $this->artisan('levels:send-renewal-reminders')->assertExitCode(0);

        $nextCourse = Course::query()
            ->where('campus_id', $campus->id)
            ->where('program_level_id', $level2->id)
            ->whereNull('teacher_id')
            ->first();

        $this->assertNotNull($nextCourse);
        $this->assertSame((int) $schedule->id, (int) $nextCourse->schedule_template_id);
        $this->assertNotNull($nextCourse->managed_group_id);
        $this->assertGreaterThan(0, ClassSession::query()->where('group_id', $nextCourse->managed_group_id)->count());
    }

    public function test_enrollment_is_blocked_when_previous_course_has_need_support(): void
    {
        $campus = Campus::query()->create(['name' => 'Campus B', 'code' => 'CB', 'status' => 'active']);
        $admin = User::factory()->create(['campus_id' => $campus->id, 'role' => 'admin', 'email' => 'admin@renewal.test']);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Student',
            'last_name' => 'Blocked',
            'email' => 'student-blocked@renewal.test',
            'status' => 'active',
        ]);

        $program = Program::query()->firstOrFail();
        $period = Period::query()->create([
            'campus_id' => $campus->id,
            'code' => '2026-Q1',
            'description' => 'Periodo Q1',
            'status' => 'active',
        ]);
        $level1 = ProgramLevel::query()->where('program_id', $program->id)->orderBy('sort_order')->firstOrFail();
        $level2 = ProgramLevel::query()->where('program_id', $program->id)->where('sort_order', '>', $level1->sort_order)->orderBy('sort_order')->firstOrFail();
        $academicLevel = AcademicLevel::query()->create(['campus_id' => $campus->id, 'name' => 'Primary']);

        $sourceCourse = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $program->id,
            'program_level_id' => $level1->id,
            'name' => 'Origen',
            'status' => 'active',
        ]);
        $sourceGroup = Group::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $sourceCourse->id,
            'name' => 'GB1',
            'period' => '2026',
            'status' => 'active',
        ]);

        $targetCourse = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $program->id,
            'program_level_id' => $level2->id,
            'name' => 'Destino',
            'status' => 'active',
        ]);
        $targetGroup = Group::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $targetCourse->id,
            'name' => 'GB2',
            'period' => '2026',
            'status' => 'active',
        ]);

        $sourceEnrollment = Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $sourceGroup->id,
            'enrolled_at' => now()->subDays(40)->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);

        $set = GradeEvaluationSet::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $sourceCourse->id,
            'group_id' => $sourceGroup->id,
            'evaluated_on' => now()->toDateString(),
            'title' => 'Final',
            'created_by' => $admin->id,
        ]);
        GradeEntry::query()->create([
            'grade_evaluation_set_id' => $set->id,
            'campus_id' => $campus->id,
            'enrollment_id' => $sourceEnrollment->id,
            'vocabulary_rating' => 'acceptable',
            'listening_rating' => 'need_support',
            'speaking_rating' => 'acceptable',
            'writing_rating' => 'regular',
            'grammar_rating' => 'regular',
            'observations' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('enrollments.store'), [
            'student_id' => $student->id,
            'group_id' => $targetGroup->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $student->id,
            'group_id' => $targetGroup->id,
        ]);
    }

    public function test_student_can_enroll_from_portal_when_renewal_offer_exists(): void
    {
        $campus = Campus::query()->create(['name' => 'Campus C', 'code' => 'CC', 'status' => 'active']);
        $studentRole = Role::query()->firstOrCreate(['name' => 'student'], ['label' => 'Alumno']);
        $portalPermission = Permission::query()->firstOrCreate(['name' => 'portal.student.view'], ['label' => 'Portal Alumno']);

        $studentUser = User::factory()->create(['campus_id' => $campus->id, 'role' => 'student', 'email' => 'portal-student@renewal.test']);
        $studentUser->roles()->syncWithoutDetaching([$studentRole->id]);
        $studentRole->permissions()->syncWithoutDetaching([$portalPermission->id]);

        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'user_id' => $studentUser->id,
            'first_name' => 'Portal',
            'last_name' => 'Student',
            'email' => $studentUser->email,
            'status' => 'active',
        ]);

        $program = Program::query()->firstOrFail();
        $period = Period::query()->create([
            'campus_id' => $campus->id,
            'code' => '2026-Q1',
            'description' => 'Periodo Q1',
            'status' => 'active',
        ]);
        $level1 = ProgramLevel::query()->where('program_id', $program->id)->orderBy('sort_order')->firstOrFail();
        $level2 = ProgramLevel::query()->where('program_id', $program->id)->where('sort_order', '>', $level1->sort_order)->orderBy('sort_order')->firstOrFail();
        $academicLevel = AcademicLevel::query()->create(['campus_id' => $campus->id, 'name' => 'Primary']);
        $schedule = ScheduleTemplate::query()->create([
            'campus_id' => $campus->id,
            'days' => ['mon', 'wed'],
            'starts_at' => '14:20:00',
            'ends_at' => '15:50:00',
            'status' => 'active',
        ]);

        $sourceCourse = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $program->id,
            'program_level_id' => $level1->id,
            'schedule_template_id' => $schedule->id,
            'period_id' => $period->id,
            'name' => 'Origen portal',
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->toDateString(),
            'academic_hours' => 40,
            'status' => 'active',
        ]);
        $sourceGroup = Group::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $sourceCourse->id,
            'name' => 'GC1',
            'period' => '2026',
            'status' => 'active',
        ]);
        $sourceCourse->update(['managed_group_id' => $sourceGroup->id]);
        $sourceEnrollment = Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $sourceGroup->id,
            'enrolled_at' => now()->subDays(20)->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);

        $targetCourse = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $program->id,
            'program_level_id' => $level2->id,
            'schedule_template_id' => $schedule->id,
            'period_id' => $period->id,
            'teacher_id' => null,
            'name' => 'Destino portal',
            'start_date' => $sourceCourse->end_date->copy()->addDay()->toDateString(),
            'academic_hours' => 40,
            'status' => 'active',
        ]);
        $targetGroup = Group::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $targetCourse->id,
            'name' => 'GC2',
            'period' => '2026',
            'status' => 'active',
        ]);
        $targetCourse->update(['managed_group_id' => $targetGroup->id]);

        $set = GradeEvaluationSet::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $sourceCourse->id,
            'group_id' => $sourceGroup->id,
            'evaluated_on' => now()->toDateString(),
            'title' => 'Final',
            'created_by' => $studentUser->id,
        ]);
        GradeEntry::query()->create([
            'grade_evaluation_set_id' => $set->id,
            'campus_id' => $campus->id,
            'enrollment_id' => $sourceEnrollment->id,
            'vocabulary_rating' => 'outstanding',
            'listening_rating' => 'acceptable',
            'speaking_rating' => 'acceptable',
            'writing_rating' => 'regular',
            'grammar_rating' => 'acceptable',
            'observations' => null,
        ]);

        $portal = $this->actingAs($studentUser)->get(route('portal.student'));
        $portal->assertOk();
        $portal->assertSee('Inscribirme en este curso');

        $enroll = $this->actingAs($studentUser)->post(route('portal.student.renewals.enroll', $targetCourse));
        $enroll->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'group_id' => $targetGroup->id,
        ]);
    }
}
