<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Program;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\User;
use App\Support\CoursePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolClosureDaysTest extends TestCase
{
    use RefreshDatabase;

    private Campus $school;

    private Campus $academy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = Campus::create(['name' => 'Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $this->academy = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
    }

    private function course(Campus $campus): Course
    {
        $schedule = ScheduleTemplate::create(['campus_id' => $campus->id, 'days' => ['mon', 'wed'], 'starts_at' => '14:20:00', 'ends_at' => '15:50:00', 'status' => 'active']);
        $program = Program::create(['name' => 'Danza '.$campus->code, 'code' => 'DZ-'.$campus->code, 'status' => 'active', 'is_extracurricular' => true]);
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $campus->id, 'name' => 'Nivel'])->id,
            'program_id' => $program->id,
            'schedule_template_id' => $schedule->id,
            'name' => 'Danza '.$campus->code,
            'start_date' => '2026-11-02',
            'end_date' => '2027-01-31',
            'status' => 'active',
        ]);
        CoursePlanner::sync($course);

        return $course->fresh();
    }

    private function campusAdmin(Campus $campus): User
    {
        return User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => false]);
    }

    private function hasSessionOn(Course $course, string $date): bool
    {
        return ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', $date)->exists();
    }

    private function closurePayload(Campus $campus, array $extra = []): array
    {
        return array_merge([
            'campus_id' => $campus->id,
            'name' => 'Vacaciones de Navidad',
            'is_recurring' => 0,
            'holiday_date' => '2026-12-14',
            'end_date' => '2027-01-08',
            'status' => 'active',
        ], $extra);
    }

    public function test_campus_admin_loads_closure_range_and_only_their_courses_change(): void
    {
        $schoolCourse = $this->course($this->school);
        $academyCourse = $this->course($this->academy);
        $this->assertTrue($this->hasSessionOn($schoolCourse, '2026-12-16'));

        $this->actingAs($this->campusAdmin($this->school))
            ->post(route('holidays.store'), $this->closurePayload($this->school, ['kind' => 'holiday']))
            ->assertRedirect(route('holidays.index'));

        $holiday = Holiday::query()->firstOrFail();
        $this->assertSame(Holiday::KIND_SCHOOL_CLOSURE, $holiday->kind, 'El admin de sede solo crea días sin clase.');
        $this->assertSame('14/12/2026 al 08/01/2027', $holiday->occurrence_label);
        $this->assertTrue(AuditLog::query()->where('action', 'holiday.create')->exists());

        foreach (['2026-12-14', '2026-12-16', '2027-01-04', '2027-01-06'] as $date) {
            $this->assertFalse($this->hasSessionOn($schoolCourse, $date), "No debería haber clase el {$date}.");
        }
        $this->assertTrue($this->hasSessionOn($schoolCourse, '2027-01-11'));
        $this->assertTrue($this->hasSessionOn($academyCourse, '2026-12-16'), 'Los cursos de otra sede no cambian.');
    }

    public function test_campus_admin_cannot_touch_global_or_other_campus_days(): void
    {
        $global = Holiday::create(['name' => 'Navidad', 'kind' => 'holiday', 'holiday_date' => '2026-12-25', 'status' => 'active']);
        $other = Holiday::create(['campus_id' => $this->academy->id, 'name' => 'Asueto Cascada', 'kind' => 'school_closure', 'holiday_date' => '2026-11-20', 'status' => 'active']);
        $admin = $this->campusAdmin($this->school);

        $this->actingAs($admin)->post(route('holidays.store'), $this->closurePayload($this->academy))->assertForbidden();
        $this->actingAs($admin)->post(route('holidays.store'), $this->closurePayload($this->school, ['campus_id' => null]))->assertSessionHasErrors('campus_id');
        $this->actingAs($admin)->get(route('holidays.edit', $global))->assertForbidden();
        $this->actingAs($admin)->delete(route('holidays.destroy', $global))->assertForbidden();
        $this->actingAs($admin)->put(route('holidays.update', $other), $this->closurePayload($this->academy))->assertForbidden();
        $this->assertSame(2, Holiday::query()->count());

        $this->actingAs($admin)->get(route('holidays.index'))
            ->assertOk()
            ->assertSee('Navidad')
            ->assertDontSee('Asueto Cascada')
            ->assertSee('Nuevo día sin clase');
    }

    public function test_attended_class_inside_closure_is_kept_and_reported(): void
    {
        $course = $this->course($this->school);
        $student = Student::create(['campus_id' => $this->school->id, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'status' => 'active']);
        $enrollment = Enrollment::create([
            'campus_id' => $this->school->id,
            'student_id' => $student->id,
            'group_id' => $course->managed_group_id,
            'enrolled_at' => '2026-11-01',
            'status' => 'active',
            'progress' => 0,
        ]);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-12-14')->firstOrFail();
        AttendanceRecord::create(['class_session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'present']);

        $this->actingAs($this->campusAdmin($this->school))
            ->post(route('holidays.store'), $this->closurePayload($this->school))
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, '14/12/2026'));

        $this->assertTrue(ClassSession::query()->whereKey($session->id)->exists());
        $this->assertFalse($this->hasSessionOn($course, '2026-12-16'));
        $this->actingAs($this->campusAdmin($this->school))->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Feriado / sin clase');
    }

    public function test_master_can_still_create_global_holiday(): void
    {
        $course = $this->course($this->academy);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->school->id, 'is_master' => true]);

        $this->actingAs($master)
            ->post(route('holidays.store'), ['name' => 'Feriado nacional', 'kind' => 'holiday', 'is_recurring' => 0, 'holiday_date' => '2026-11-04', 'status' => 'active'])
            ->assertRedirect(route('holidays.index'));

        $holiday = Holiday::query()->firstOrFail();
        $this->assertNull($holiday->campus_id);
        $this->assertSame(Holiday::KIND_HOLIDAY, $holiday->kind);
        $this->assertFalse($this->hasSessionOn($course, '2026-11-04'));
    }
}
