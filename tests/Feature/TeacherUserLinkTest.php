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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherUserLinkTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sessionFor(Teacher $teacher): ClassSession
    {
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher->id,
            'name' => 'HS5A - Teacher '.$teacher->id,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id, 'name' => 'HS5A-'.$teacher->id, 'status' => 'active', 'capacity' => 30]);

        return ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-10-08', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
    }

    /** Caso Sofía: usuario viejo con ficha inactiva, ficha activa con el email nuevo y sin usuario. */
    private function sofiaCase(): array
    {
        $oldUser = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id, 'email' => 'sofia.old@test.com']);
        Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Sofia', 'last_name' => 'nava', 'user_id' => $oldUser->id, 'status' => 'inactive']);
        $newUser = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false, 'email' => 'baron@test.com']);
        $active = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'SOFIA', 'last_name' => 'BARON NAVA', 'email' => 'Baron@test.com', 'status' => 'active']);

        return [$oldUser, $newUser, $active];
    }

    public function test_active_teacher_found_by_email_wins_over_inactive_link_and_gets_linked(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id, 'email' => 'ana@test.com']);
        Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Vieja', 'user_id' => $user->id, 'status' => 'inactive']);
        $active = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Nueva', 'email' => 'ANA@test.com', 'status' => 'active']);
        $session = $this->sessionFor($active);

        $this->actingAs($user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('value="'.$session->id.'"', false);

        $this->assertSame($user->id, (int) $active->fresh()->user_id);
    }

    public function test_changing_teacher_email_moves_the_login_with_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $user = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id, 'email' => 'luis@old.com']);
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Luis', 'last_name' => 'Rojas', 'email' => 'luis@old.com', 'user_id' => $user->id, 'status' => 'active']);
        $session = $this->sessionFor($teacher);

        $this->actingAs($admin)->get(route('teachers.edit', $teacher))->assertOk()->assertSee('Acceso a la plataforma: luis@old.com');

        $this->actingAs($admin)->put(route('teachers.update', $teacher), [
            'campus_id' => $this->campus->id,
            'first_name' => 'Luis',
            'last_name' => 'Rojas',
            'email' => 'luis@new.com',
            'status' => 'active',
        ])->assertSessionHas('success', 'Profesor actualizado. Ahora ingresa con luis@new.com.');

        $this->assertSame('luis@new.com', $user->fresh()->email);
        $this->actingAs($user->fresh())->get(route('attendance.index'))->assertSee('value="'.$session->id.'"', false);
    }

    public function test_command_fixes_sofia_case(): void
    {
        [$oldUser, $newUser, $active] = $this->sofiaCase();
        $session = $this->sessionFor($active);
        $options = ['--as-teacher' => ['baron@test.com'], '--deactivate' => ['sofia.old@test.com']];

        $this->artisan('teachers:link-users', $options + ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Cambios: 3')
            ->assertSuccessful();
        $this->assertSame('admin', $newUser->fresh()->role);

        $this->artisan('teachers:link-users', $options)->assertSuccessful();

        $this->assertSame('teacher', $newUser->fresh()->role);
        $this->assertSame($newUser->id, (int) $active->fresh()->user_id);
        $this->assertSame('inactive', $oldUser->fresh()->status);

        $this->actingAs($newUser->fresh())->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('value="'.$session->id.'"', false);
    }

    public function test_command_does_not_link_teacher_to_admin_user_without_flag(): void
    {
        [, , $active] = $this->sofiaCase();

        $this->artisan('teachers:link-users')
            ->expectsOutputToContain('su email pertenece a un usuario con rol admin')
            ->assertSuccessful();

        $this->assertNull($active->fresh()->user_id);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'off@test.com', 'password' => Hash::make('secret123'), 'status' => 'inactive', 'role' => 'teacher']);

        $this->post(route('login'), ['email' => 'off@test.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
