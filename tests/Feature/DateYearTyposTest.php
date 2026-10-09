<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Group;
use App\Models\Period;
use App\Models\ProgramLevel;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DateYearTyposTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 10:00:00');
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dates_with_wrong_year_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Test', 'status' => 'active']);

        $this->actingAs($admin)->post(route('students.charges.store', $student), [
            'concept' => 'Material',
            'amount' => 10,
            'due_date' => '0026-10-15',
            'status' => 'pending',
        ])->assertSessionHasErrors(['due_date' => 'Revisa el año de la fecha: debe estar entre 2020 y 2100.']);

        $this->actingAs($admin)->post(route('students.charges.store', $student), [
            'concept' => 'Material',
            'amount' => 10,
            'due_date' => '2026-10-15',
            'status' => 'pending',
        ])->assertSessionHasNoErrors();
    }

    public function test_command_fixes_course_with_year_0026(): void
    {
        $level = ProgramLevel::query()->firstOrFail();
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Sofia', 'last_name' => 'Baron', 'status' => 'active']);
        $period = Period::create(['campus_id' => $this->campus->id, 'code' => '2026-Q4', 'status' => 'active']);
        $schedule = ScheduleTemplate::create(['campus_id' => $this->campus->id, 'days' => ['sat'], 'starts_at' => '08:00:00', 'ends_at' => '11:30:00', 'status' => 'active']);
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'schedule_template_id' => $schedule->id,
            'academic_hours' => 40,
            'name' => 'HS1B - Horario: S 8.00 11.30',
            'start_date' => '2026-10-03',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id, 'name' => 'HS1B', 'status' => 'active', 'capacity' => 30, 'start_date' => '2026-10-03']);
        $course->update(['managed_group_id' => $group->id]);
        $session = ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-10-03', 'starts_at' => '08:00:00', 'ends_at' => '11:30:00']);

        // Datos como quedaron en producción: año escrito como 0026.
        DB::table('courses')->where('id', $course->id)->update(['start_date' => '0026-10-03']);
        DB::table('groups')->where('id', $group->id)->update(['start_date' => '0026-10-03']);
        DB::table('class_sessions')->where('id', $session->id)->update(['session_date' => '0026-10-03']);

        $this->artisan('data:fix-date-year-typos', ['--dry-run' => true])
            ->expectsOutputToContain('courses #'.$course->id.' start_date: 0026-10-03 → 2026-10-03')
            ->expectsOutputToContain('[dry-run] Fechas corregidas: 3')
            ->assertSuccessful();
        $this->assertStringStartsWith('0026', (string) DB::table('courses')->where('id', $course->id)->value('start_date'));

        $this->artisan('data:fix-date-year-typos')->assertSuccessful();

        $this->assertSame('2026-10-03', $course->fresh()->start_date->toDateString());
        $sessions = ClassSession::query()->where('group_id', $group->id)->orderBy('session_date')->get();
        $this->assertGreaterThan(0, $sessions->count());
        $this->assertSame('2026-10-03', $sessions->first()->session_date->toDateString());
        $this->assertTrue($sessions->every(fn ($item) => $item->session_date->year === 2026 && $item->session_date->isSaturday()));
    }
}
