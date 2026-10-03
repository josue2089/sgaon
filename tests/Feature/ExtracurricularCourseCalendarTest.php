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
use App\Models\ProgramLevel;
use App\Models\Teacher;
use App\Models\Program;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\User;
use App\Support\CoursePlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExtracurricularCourseCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private ScheduleTemplate $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus = Campus::create(['name' => 'Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $this->schedule = ScheduleTemplate::create([
            'campus_id' => $this->campus->id,
            'days' => ['mon', 'wed'],
            'starts_at' => '14:20:00',
            'ends_at' => '15:50:00',
            'status' => 'active',
        ]);
    }

    private function course(bool $extracurricular, array $attributes = []): Course
    {
        $program = Program::create([
            'name' => $extracurricular ? 'Danza' : 'Inglés regular',
            'code' => $extracurricular ? 'DANZA-'.uniqid() : 'REG-'.uniqid(),
            'status' => 'active',
            'is_extracurricular' => $extracurricular,
        ]);
        $level = AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'Nivel']);

        return Course::create(array_merge([
            'campus_id' => $this->campus->id,
            'academic_level_id' => $level->id,
            'program_id' => $program->id,
            'schedule_template_id' => $this->schedule->id,
            'name' => 'Curso',
            'start_date' => '2026-09-07',
            'status' => 'active',
        ], $attributes));
    }

    private function sessionDates(Course $course): array
    {
        return ClassSession::query()
            ->where('group_id', $course->fresh()->managed_group_id)
            ->orderBy('session_date')
            ->pluck('session_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();
    }

    public function test_extracurricular_course_generates_every_class_until_end_date(): void
    {
        Holiday::create(['campus_id' => $this->campus->id, 'name' => 'Asueto colegio', 'holiday_date' => '2026-10-12', 'status' => 'active']);
        $otherCampus = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        Holiday::create(['campus_id' => $otherCampus->id, 'name' => 'Solo Cascada', 'holiday_date' => '2026-10-14', 'status' => 'active']);

        $course = $this->course(true, ['end_date' => '2027-07-15']);
        CoursePlanner::sync($course);

        $expected = CoursePlanner::scheduleDatesBetween(
            Carbon::parse('2026-09-07'),
            Carbon::parse('2027-07-15'),
            $this->schedule,
            CoursePlanner::holidaysFor($course),
        )->map->toDateString()->all();
        $dates = $this->sessionDates($course);

        $this->assertSame($expected, $dates);
        $this->assertGreaterThan(80, count($dates));
        $this->assertNotContains('2026-10-12', $dates);
        $this->assertContains('2026-10-14', $dates);
        $this->assertSame('2027-07-14', end($dates));
        $this->assertSame('2027-07-15', $course->fresh()->end_date->toDateString());
    }

    public function test_changing_end_date_adds_or_removes_classes_but_keeps_attended_ones(): void
    {
        $course = $this->course(true, ['end_date' => '2026-10-31']);
        CoursePlanner::sync($course);
        $course->refresh();

        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'status' => 'active']);
        $enrollment = Enrollment::create([
            'campus_id' => $this->campus->id,
            'student_id' => $student->id,
            'group_id' => $course->managed_group_id,
            'enrolled_at' => '2026-09-01',
            'status' => 'active',
            'progress' => 0,
        ]);
        $attended = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-10-28')->firstOrFail();
        AttendanceRecord::create(['class_session_id' => $attended->id, 'enrollment_id' => $enrollment->id, 'status' => 'present']);

        $course->update(['end_date' => '2026-12-16']);
        CoursePlanner::sync($course->fresh());
        $this->assertSame('2026-12-16', last($this->sessionDates($course)));

        $course->update(['end_date' => '2026-10-15']);
        CoursePlanner::sync($course->fresh());
        $dates = $this->sessionDates($course);

        $this->assertSame('2026-10-28', last($dates), 'La clase con asistencia no se borra aunque quede después de la fecha de fin.');
        $this->assertNotContains('2026-10-26', $dates);
        $this->assertTrue(ClassSession::query()->whereKey($attended->id)->exists());
    }

    public function test_extra_session_is_added_and_survives_recalculation(): void
    {
        $course = $this->course(true, ['end_date' => '2026-10-31']);
        CoursePlanner::sync($course);
        $course->refresh();
        $before = count($this->sessionDates($course));

        $extra = CoursePlanner::addExtraSession($course->load(['scheduleTemplate', 'managedGroup']), Carbon::parse('2026-09-12'), '09:00', '10:30');
        $this->assertTrue($extra->is_extra);
        $this->assertCount($before + 1, $this->sessionDates($course));

        CoursePlanner::sync($course->fresh());
        $dates = $this->sessionDates($course);
        $this->assertCount($before + 1, $dates);
        $this->assertContains('2026-09-12', $dates);

        $this->expectException(ValidationException::class);
        CoursePlanner::addExtraSession($course->fresh(['scheduleTemplate', 'managedGroup']), Carbon::parse('2026-09-12'), '09:00', '10:30');
    }

    public function test_regular_course_still_uses_academic_hours(): void
    {
        // 30 horas académicas × 45 min / 90 min por clase = 15 clases.
        $course = $this->course(false, ['academic_hours' => 30]);
        CoursePlanner::sync($course);

        $this->assertCount(15, $this->sessionDates($course));
        $this->assertSame(last($this->sessionDates($course)), $course->fresh()->end_date->toDateString());
    }

    public function test_admin_adds_extra_class_from_course_page(): void
    {
        $course = $this->course(true, ['end_date' => '2026-10-31']);
        CoursePlanner::sync($course);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);

        $this->actingAs($admin)->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Agregar clase extra');

        $this->actingAs($admin)
            ->post(route('courses.extra-sessions.store', $course), ['session_date' => '2026-09-19'])
            ->assertRedirect(route('courses.show', $course));

        $this->assertContains('2026-09-19', $this->sessionDates($course));
        $this->actingAs($admin)->get(route('courses.show', $course))->assertSee('Clase extra');
    }

    public function test_course_form_requires_end_date_for_extracurricular_and_hours_for_regular(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $level = ProgramLevel::query()->firstOrFail();
        $level->program->update(['is_extracurricular' => true]);
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Laura', 'last_name' => 'Quintero', 'status' => 'active']);
        $period = Period::create(['campus_id' => $this->campus->id, 'code' => '2026-2027', 'status' => 'active']);
        $payload = [
            'campus_id' => $this->campus->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'schedule_template_id' => $this->schedule->id,
            'start_date' => '2026-09-07',
            'status' => 'active',
        ];

        $this->actingAs($admin)->get(route('courses.create'))
            ->assertOk()
            ->assertSee('Fecha de fin (fin del año escolar)');

        $this->actingAs($admin)->post(route('courses.store'), $payload)->assertSessionHasErrors('end_date');

        $this->actingAs($admin)->post(route('courses.store'), $payload + ['end_date' => '2027-07-15', 'academic_hours' => 40])
            ->assertSessionHasNoErrors();
        $course = Course::query()->where('program_level_id', $level->id)->latest('id')->firstOrFail();
        $this->assertNull($course->academic_hours);
        $this->assertSame('2027-07-15', $course->end_date->toDateString());
        $this->assertGreaterThan(80, count($this->sessionDates($course)));

        $level->program->update(['is_extracurricular' => false]);
        $this->actingAs($admin)->post(route('courses.store'), $payload)->assertSessionHasErrors('academic_hours');
    }
}
