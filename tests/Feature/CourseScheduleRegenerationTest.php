<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\GradeEvaluationSet;
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

class CourseScheduleRegenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_template_update_syncs_session_times_and_preserves_attendance(): void
    {
        [$admin, $course, $enrollment, $schedule] = $this->courseWithFridaySchedule('14:20:00', '17:40:00');

        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->firstOrFail();
        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $this->assertSame(9, ClassSession::query()->where('group_id', $course->managed_group_id)->count());

        $this->actingAs($admin)->put(route('schedules.update', $schedule), [
            'campus_id' => $schedule->campus_id,
            'days' => ['fri'],
            'starts_at' => '14:30',
            'ends_at' => '17:40',
            'status' => 'active',
        ])->assertRedirect(route('schedules.index'));

        $course->refresh();
        $sessions = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->get();

        $this->assertSame(10, $sessions->count());
        $sessions->each(function (ClassSession $row): void {
            $this->assertSame('14:30:00', CoursePlanner::normalizeTime($row->starts_at));
            $this->assertSame('17:40:00', CoursePlanner::normalizeTime($row->ends_at));
        });

        $this->assertSame(1, AttendanceRecord::query()->where('enrollment_id', $enrollment->id)->count());
        $this->assertSame($session->id, AttendanceRecord::query()->where('enrollment_id', $enrollment->id)->value('class_session_id'));
    }

    public function test_course_update_resyncs_when_session_times_differ_from_template(): void
    {
        [$admin, $course, $enrollment] = $this->courseWithFridaySchedule('14:20:00', '17:40:00');

        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->firstOrFail();
        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $schedule = ScheduleTemplate::query()->findOrFail($course->schedule_template_id);
        $schedule->update(['starts_at' => '14:30:00']);

        $response = $this->actingAs($admin)->put(route('courses.update', $course), $this->coursePayload($course));

        $response->assertRedirect(route('courses.show', $course));

        $firstSession = ClassSession::query()->findOrFail($session->id);
        $this->assertSame('14:30:00', CoursePlanner::normalizeTime($firstSession->starts_at));
        $this->assertSame(1, AttendanceRecord::query()->where('class_session_id', $session->id)->count());
    }

    public function test_schedule_regeneration_preserves_grade_evaluations(): void
    {
        [$admin, $course, $enrollment, $schedule] = $this->courseWithFridaySchedule('14:20:00', '17:40:00');

        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->firstOrFail();
        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $set = GradeEvaluationSet::query()->create([
            'campus_id' => $course->campus_id,
            'course_id' => $course->id,
            'group_id' => $course->managed_group_id,
            'evaluated_on' => $session->session_date,
            'title' => 'Evaluación 1',
        ]);
        GradeEntry::query()->create([
            'campus_id' => $course->campus_id,
            'grade_evaluation_set_id' => $set->id,
            'enrollment_id' => $enrollment->id,
            'vocabulary_rating' => 'outstanding',
            'listening_rating' => 'acceptable',
            'speaking_rating' => 'acceptable',
            'writing_rating' => 'acceptable',
            'grammar_rating' => 'acceptable',
            'observations' => 'Bien',
        ]);

        $this->actingAs($admin)->put(route('schedules.update', $schedule), [
            'campus_id' => $schedule->campus_id,
            'days' => ['fri'],
            'starts_at' => '14:30',
            'ends_at' => '17:40',
            'status' => 'active',
        ]);

        $this->assertSame(1, GradeEvaluationSet::query()->where('course_id', $course->id)->count());
        $this->assertSame(1, GradeEntry::query()->where('enrollment_id', $enrollment->id)->count());
    }

    public function test_protected_session_keeps_program_notes_when_schedule_changes(): void
    {
        [$admin, $course, $enrollment, $schedule] = $this->courseWithFridaySchedule('14:20:00', '17:40:00');

        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->firstOrFail();
        $session->update([
            'topic' => 'Tema visto',
            'program_status' => 'on_track',
            'program_notes' => 'Avanzamos unidad 1',
        ]);
        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $this->actingAs($admin)->put(route('schedules.update', $schedule), [
            'campus_id' => $schedule->campus_id,
            'days' => ['fri'],
            'starts_at' => '14:30',
            'ends_at' => '17:40',
            'status' => 'active',
        ]);

        $session->refresh();
        $this->assertSame('Tema visto', $session->topic);
        $this->assertSame('on_track', $session->program_status);
        $this->assertSame('Avanzamos unidad 1', $session->program_notes);
    }

    /**
     * @return array{0: User, 1: Course, 2: Enrollment, 3: ScheduleTemplate}
     */
    private function courseWithFridaySchedule(string $startsAt, string $endsAt): array
    {
        $campus = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $admin = User::factory()->create(['campus_id' => $campus->id, 'role' => 'admin', 'is_master' => true]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $teacher = Teacher::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Teacher',
            'last_name' => 'Friday',
            'email' => 'teacher-fri@test.dev',
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
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
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
        $course->refresh()->load('managedGroup');

        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Student',
            'last_name' => 'One',
            'status' => 'active',
        ]);
        $enrollment = Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $course->managed_group_id,
            'enrolled_at' => '2026-06-05',
            'status' => 'active',
            'progress' => 0,
        ]);

        return [$admin, $course->fresh(['teacher', 'period', 'scheduleTemplate', 'programLevel']), $enrollment, $schedule];
    }

    private function coursePayload(Course $course): array
    {
        return [
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
        ];
    }
}
