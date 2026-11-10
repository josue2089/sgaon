<?php

namespace Tests\Feature;

use App\Mail\PortalInvitationMail;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeacherAccessAndDataHealthTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
    }

    private function teacher(array $attributes = []): Teacher
    {
        return Teacher::create(array_merge(['campus_id' => $this->campus->id, 'first_name' => 'Filip', 'last_name' => 'Rachev', 'email' => 'filip@test.com', 'status' => 'active'], $attributes));
    }

    private function invitationUrl(string $to): string
    {
        $url = null;
        Mail::assertQueued(PortalInvitationMail::class, function (PortalInvitationMail $mail) use ($to, &$url) {
            if ($mail->hasTo($to)) {
                $url = $mail->url;
            }

            return $mail->hasTo($to);
        });

        return $url;
    }

    public function test_admin_invites_teacher_who_activates_and_logs_in(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($this->master)->get(route('teachers.show', $teacher))
            ->assertOk()->assertSee('Acceso a la plataforma')->assertSee('Invitar');
        $this->actingAs($this->master)->post(route('teachers.access.invite', $teacher))->assertSessionHas('success');

        $user = $teacher->fresh()->user;
        $this->assertSame('teacher', $user->role);
        $this->assertTrue(AuditLog::query()->where('action', 'teacher.access.invite')->exists());

        $url = $this->invitationUrl('filip@test.com');
        $this->app['auth']->forgetGuards();
        $this->post(route('logout'));
        $this->post($url, ['password' => 'clave-segura-1', 'password_confirmation' => 'clave-segura-1'])->assertRedirect(route('login'));
        $this->post(route('login.attempt'), ['login' => 'filip@test.com', 'password' => 'clave-segura-1'])->assertRedirect(route('dashboard'));

        $this->post(route('logout'));
        $this->actingAs($this->master)->get(route('teachers.show', $teacher))->assertSee('Activo');
        $this->actingAs($this->master)->post(route('teachers.access.deactivate', $teacher))->assertSessionHas('success');
        $this->assertSame('inactive', $user->fresh()->status);
    }

    public function test_teacher_without_email_or_with_email_of_another_role_is_blocked(): void
    {
        $noEmail = $this->teacher(['email' => null]);
        $this->actingAs($this->master)->get(route('teachers.show', $noEmail))->assertSee('El profesor no tiene correo.');
        $this->actingAs($this->master)->post(route('teachers.access.invite', $noEmail))->assertSessionHasErrors('portal_access');

        User::factory()->create(['role' => 'representative', 'email' => 'marta@test.com', 'campus_id' => $this->campus->id]);
        $shared = $this->teacher(['first_name' => 'Marta', 'email' => 'marta@test.com']);
        $this->actingAs($this->master)->post(route('teachers.access.invite', $shared))->assertSessionHasErrors('portal_access');
        $this->assertNull($shared->fresh()->user_id);
        Mail::assertNothingQueued();
    }

    public function test_existing_teacher_user_with_same_email_is_linked_not_duplicated(): void
    {
        $existing = User::factory()->create(['role' => 'teacher', 'email' => 'filip@test.com', 'campus_id' => $this->campus->id]);
        $teacher = $this->teacher();

        $this->actingAs($this->master)->post(route('teachers.access.invite', $teacher))->assertSessionHas('success');

        $this->assertSame($existing->id, $teacher->fresh()->user_id);
        $this->assertSame(1, User::query()->where('email', 'filip@test.com')->count());
    }

    public function test_bulk_command_has_dry_run_and_skips_blocked_teachers(): void
    {
        $this->teacher();
        $this->teacher(['first_name' => 'Sofia', 'email' => 'sofia@test.com']);
        $this->teacher(['first_name' => 'Sin', 'email' => null]);

        $this->artisan('teachers:invite-portal', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Se invitaría a 2.')
            ->assertSuccessful();
        Mail::assertNothingQueued();

        $this->artisan('teachers:invite-portal')->expectsOutputToContain('Profesores invitados: 2.')->assertSuccessful();
        Mail::assertQueued(PortalInvitationMail::class, 2);
        $this->artisan('teachers:invite-portal')->expectsOutputToContain('Profesores invitados: 0.')->assertSuccessful();
    }

    public function test_data_health_is_master_only_and_reconciles_charges(): void
    {
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'status' => 'active']);
        $charge = Charge::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'concept' => 'Mensualidad', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => now()->subDays(10), 'status' => 'pending']);
        $this->teacher();

        $this->actingAs($this->master)->get(route('data-health.index'))
            ->assertOk()
            ->assertSee('Salud de datos')
            ->assertSee('1 por recalcular')
            ->assertSee('Profesores activos sin acceso:');

        $this->actingAs($this->master)->post(route('data-health.reconcile-charges'))->assertSessionHas('success');
        $this->assertSame('overdue', $charge->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'data_health.reconcile_charges')->exists());
        $this->actingAs($this->master)->get(route('data-health.index'))->assertSee('Al día');

        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $this->actingAs($admin)->get(route('data-health.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('data-health.reconcile-charges'))->assertForbidden();
    }
}
