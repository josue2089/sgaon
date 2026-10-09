<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Group;
use App\Models\ProgramLevel;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceSessionPickerTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $this->group = $this->group('HS4A');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function group(string $name, ?Teacher $teacher = null): Group
    {
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher?->id,
            'name' => $name.' curso',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);

        return Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'teacher_id' => $teacher?->id, 'name' => $name, 'status' => 'active', 'capacity' => 30]);
    }

    private function classSession(string $date, ?Group $group = null): ClassSession
    {
        $group ??= $this->group;

        return ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => $date, 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
    }

    public function test_picker_shows_today_and_recent_sessions_not_far_future(): void
    {
        $today = $this->classSession('2026-10-08');
        $next = $this->classSession('2026-10-12');
        $past = $this->classSession('2026-10-01');
        $old = $this->classSession('2026-08-01');
        foreach (range(1, 120) as $i) {
            $this->classSession(now()->addDays(10 + $i)->toDateString());
        }

        $this->actingAs($this->admin)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('label="Hoy"', false)
            ->assertSee('value="'.$today->id.'"', false)
            ->assertSee('value="'.$next->id.'"', false)
            ->assertSee('value="'.$past->id.'"', false)
            ->assertDontSee('value="'.$old->id.'"', false)
            ->assertDontSee('/2027')
            ->assertSee('Hoy hay 1 clase.');
    }

    public function test_date_filter_lists_sessions_of_that_day(): void
    {
        $future = $this->classSession('2027-01-20');
        $this->classSession('2026-10-08');

        $this->actingAs($this->admin)->get(route('attendance.index', ['date' => '2027-01-20']))
            ->assertOk()
            ->assertSee('value="'.$future->id.'"', false)
            ->assertSee('label="20/01/2027"', false)
            ->assertDontSee('label="Hoy"', false);
    }

    public function test_selected_session_outside_range_stays_in_picker(): void
    {
        $old = $this->classSession('2026-08-01');

        $this->actingAs($this->admin)->get(route('attendance.index', ['class_session_id' => $old->id]))
            ->assertOk()
            ->assertSee('value="'.$old->id.'" selected', false);
    }

    public function test_teacher_only_sees_own_groups(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]);
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Sofia', 'last_name' => 'Baron', 'user_id' => $user->id, 'status' => 'active']);
        $mine = $this->classSession('2026-10-08', $this->group('HS1B', $teacher));
        $other = $this->classSession('2026-10-08');

        $this->actingAs($user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('value="'.$mine->id.'"', false)
            ->assertDontSee('value="'.$other->id.'"', false);
    }
}
