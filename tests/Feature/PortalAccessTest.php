<?php

namespace Tests\Feature;

use App\Mail\PortalInvitationMail;
use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\PortalInvitation;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->artisan('programs:apply-cefr-structure')->assertSuccessful();
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
    }

    private function student(string $levelCode, array $attributes = []): Student
    {
        $level = ProgramLevel::query()->where('code', $levelCode)->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $this->campus->id, 'name' => 'Nivel'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => 'Curso '.uniqid(),
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'name' => 'G '.uniqid(), 'status' => 'active', 'capacity' => 20]);
        $student = Student::create($attributes + ['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'status' => 'active']);
        Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);

        return $student;
    }

    private function representative(Student $student, array $attributes = []): Representative
    {
        $representative = Representative::create($attributes + ['campus_id' => $this->campus->id, 'first_name' => 'Marta', 'last_name' => 'Gil', 'email' => 'marta@test.com', 'document_id' => 'V-11.222.333']);
        $representative->students()->attach($student->id);

        return $representative;
    }

    private function invite(Student $student, string $kind, int $id)
    {
        return $this->actingAs($this->admin)->post(route('students.portal-access.invite', $student), ['kind' => $kind, 'id' => $id]);
    }

    private function logout(): void
    {
        Auth::logout();
        $this->app['auth']->forgetGuards();
    }

    /** Enlace del último correo de invitación enviado a esa dirección. */
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

    private function activate(string $url, string $password = 'clave-segura-1')
    {
        return $this->post($url, ['password' => $password, 'password_confirmation' => $password]);
    }

    private function login(string $login, string $password = 'clave-segura-1')
    {
        return $this->post(route('login.attempt'), ['login' => $login, 'password' => $password]);
    }

    public function test_representative_activates_and_logs_in_with_email_or_document(): void
    {
        $student = $this->student('PRIMARY2A');
        $representative = $this->representative($student);

        $this->invite($student, 'representative', $representative->id)->assertSessionHasNoErrors()->assertSessionHas('success');

        $user = $representative->fresh()->user;
        $this->assertSame('marta@test.com', $user->email);
        $this->assertSame('11222333', $user->login_document);
        $this->assertSame('representative', $user->role);
        $this->assertTrue($user->roles()->where('name', 'representative')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'portal.access.invite')->exists());

        // La cuenta no sirve hasta elegir la contraseña.
        $this->logout();
        $this->login('marta@test.com')->assertSessionHasErrors('login');

        $url = $this->invitationUrl('marta@test.com');
        $this->get($url)->assertOk()->assertSee('marta@test.com');
        $this->activate($url)->assertRedirect(route('login'));

        $this->login('marta@test.com')->assertRedirect(route('dashboard'));
        $this->get(route('portal.representative'))->assertOk()->assertSee('Ana Gil');
        $this->logout();
        $this->login('V-11.222.333')->assertRedirect(route('dashboard'));
        $this->logout();

        // El enlace es de un solo uso.
        $this->get($url)->assertStatus(410);
        $this->activate($url, 'otra-clave-22')->assertRedirect($url);
        $this->login('marta@test.com')->assertRedirect(route('dashboard'));
    }

    public function test_high_school_student_gets_an_account_and_primary_does_not(): void
    {
        $teen = $this->student('HS3A', ['first_name' => 'Luis', 'email' => 'luis@test.com']);
        $kid = $this->student('PRIMARY2A', ['first_name' => 'Nina', 'email' => 'nina@test.com']);

        $this->invite($teen, 'student', $teen->id)->assertSessionHasNoErrors();
        $this->assertSame('student', $teen->fresh()->user->role);

        $this->invite($kid, 'student', $kid->id)->assertSessionHasErrors('portal_access');
        $this->assertNull($kid->fresh()->user_id);
        $this->assertNull(User::query()->where('email', 'nina@test.com')->first());

        $this->actingAs($this->admin)->get(route('students.show', $kid))
            ->assertOk()
            ->assertSee('entra por el portal del representante');

        $this->logout();
        $this->activate($this->invitationUrl('luis@test.com'));
        $this->login('luis@test.com')->assertRedirect(route('dashboard'));
        $this->get(route('portal.student'))->assertOk()->assertSee('Luis Gil');
    }

    public function test_shared_email_student_logs_in_with_document_and_invitation_goes_to_representative(): void
    {
        // Ficha ajena sin usuario ni correo: una cuenta sin correo nunca debe verla.
        Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ajena', 'last_name' => 'Sinusuario', 'status' => 'active']);
        $student = $this->student('HS3A', ['first_name' => 'Luis', 'email' => 'MARTA@test.com ', 'document_id' => '30.111.222']);
        $representative = $this->representative($student);

        $this->invite($student, 'representative', $representative->id)->assertSessionHasNoErrors();
        $this->invite($student, 'student', $student->id)->assertSessionHasNoErrors();

        $studentUser = $student->fresh()->user;
        $this->assertNull($studentUser->email);
        $this->assertSame('30111222', $studentUser->login_document);
        $this->assertNotSame($representative->fresh()->user_id, $studentUser->id);
        $this->assertSame('marta@test.com', PortalInvitation::query()->where('user_id', $studentUser->id)->value('sent_to'));

        $this->logout();
        $url = route('portal.activate', 'x');
        Mail::assertQueued(PortalInvitationMail::class, function (PortalInvitationMail $mail) use ($studentUser, &$url) {
            if ((int) $mail->user->id === (int) $studentUser->id) {
                $url = $mail->url;
                $this->assertTrue($mail->sentToRepresentative);
            }

            return true;
        });
        $this->activate($url);

        $this->login('30111222')->assertRedirect(route('dashboard'));
        $this->get(route('portal.student'))->assertOk()->assertSee('Luis Gil')->assertDontSee('Ajena');
    }

    public function test_student_without_own_email_or_document_cannot_be_invited(): void
    {
        $student = $this->student('HS3A', ['document_id' => '11222333']);
        $this->representative($student);

        // Su cédula es la del representante: no lo identifica.
        $this->invite($student, 'student', $student->id)->assertSessionHasErrors('portal_access');
        $this->assertNull($student->fresh()->user_id);
    }

    public function test_invitation_expires_after_seven_days_and_can_be_resent(): void
    {
        $student = $this->student('PRIMARY2A');
        $representative = $this->representative($student);
        $this->invite($student, 'representative', $representative->id);
        $old = $this->invitationUrl('marta@test.com');

        $this->travel(8)->days();
        $this->actingAs($this->admin)->get(route('students.show', $student))->assertSee('Invitación vencida');
        $this->logout();
        $this->get($old)->assertStatus(410);
        $this->activate($old)->assertRedirect($old);
        $this->login('marta@test.com')->assertSessionHasErrors('login');

        $this->invite($student, 'representative', $representative->id)->assertSessionHasNoErrors();
        $this->assertSame(1, PortalInvitation::query()->whereNull('accepted_at')->count());
        $this->logout();
        $this->get($this->invitationUrl('marta@test.com'))->assertOk();
    }

    public function test_forgot_password_is_neutral_and_does_not_create_families(): void
    {
        Notification::fake();
        $student = $this->student('HS3A', ['email' => 'luis@test.com', 'document_id' => '30111222']);
        $this->representative($student);
        $message = 'Si los datos corresponden a una cuenta';

        foreach (['nadie@test.com', 'luis@test.com', 'marta@test.com', '99999999'] as $login) {
            $this->post(route('password.email'), ['login' => $login])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', fn (string $status) => str_contains($status, $message));
        }
        $this->assertSame(1, User::query()->count());

        $withEmail = User::factory()->create(['role' => 'representative', 'email' => 'ya@test.com']);
        $this->post(route('password.email'), ['login' => 'ya@test.com'])->assertSessionHas('status');
        Notification::assertSentTo($withEmail, ResetPasswordNotification::class);
    }

    public function test_forgot_password_without_own_email_goes_to_the_representative(): void
    {
        $student = $this->student('HS3A', ['document_id' => '30111222']);
        $representative = $this->representative($student);
        $this->invite($student, 'student', $student->id);
        $this->logout();
        PortalInvitation::query()->update(['accepted_at' => now()]);

        $this->post(route('password.email'), ['login' => 'V-30.111.222'])->assertSessionHas('status');

        $recovery = PortalInvitation::query()->where('purpose', PortalInvitation::PURPOSE_RECOVERY)->sole();
        $this->assertSame($representative->email, $recovery->sent_to);
        $this->assertTrue($recovery->expires_at->lessThanOrEqualTo(now()->addHour()));
    }

    public function test_teacher_without_user_still_gets_access_from_forgot_password(): void
    {
        Notification::fake();
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Filip', 'last_name' => 'Rachev', 'email' => 'filip@test.com', 'status' => 'active']);

        $this->post(route('password.email'), ['login' => 'filip@test.com'])->assertSessionHas('status');

        $user = $teacher->fresh()->user;
        $this->assertSame('teacher', $user->role);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_deactivated_user_loses_access_and_can_be_reactivated(): void
    {
        $student = $this->student('PRIMARY2A');
        $representative = $this->representative($student);
        $this->invite($student, 'representative', $representative->id);
        $url = $this->invitationUrl('marta@test.com');
        $this->logout();
        $this->activate($url);
        $user = $representative->fresh()->user;

        // Con la sesión abierta, al desactivar sale del portal.
        $this->login('marta@test.com')->assertRedirect(route('dashboard'));
        $user->forceFill(['status' => 'inactive'])->save();
        $this->app['auth']->forgetGuards();
        $this->get(route('portal.representative'))->assertRedirect(route('login'));
        $this->logout();
        $user->forceFill(['status' => 'active'])->save();

        $this->actingAs($this->admin)->post(route('students.portal-access.deactivate', $student), ['kind' => 'representative', 'id' => $representative->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $user->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'portal.access.deactivate')->exists());
        $this->actingAs($this->admin)->get(route('students.show', $student))->assertSee('Reactivar e invitar');

        $this->logout();
        $this->login('marta@test.com')->assertSessionHasErrors('login');

        $this->invite($student, 'representative', $representative->id)->assertSessionHasNoErrors();
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_admin_from_another_campus_cannot_manage_access(): void
    {
        $student = $this->student('PRIMARY2A');
        $representative = $this->representative($student);
        $other = Campus::create(['name' => 'Otra', 'code' => 'OTR', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'admin', 'campus_id' => $other->id, 'is_master' => false]);

        $this->actingAs($outsider)->post(route('students.portal-access.invite', $student), ['kind' => 'representative', 'id' => $representative->id])
            ->assertForbidden();

        $foreign = Representative::create(['campus_id' => $this->campus->id, 'first_name' => 'Otro', 'last_name' => 'Rep', 'email' => 'otro@test.com']);
        $this->invite($student, 'representative', $foreign->id)->assertNotFound();
        $this->assertSame(0, PortalInvitation::query()->count());
    }

    public function test_user_changes_own_password(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'email' => 'p@test.com', 'password' => 'clave-actual-1']);

        $this->actingAs($user)->get(route('account.password.edit'))->assertOk();
        $this->put(route('account.password.update'), ['current_password' => 'mala', 'password' => 'clave-nueva-22', 'password_confirmation' => 'clave-nueva-22'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('account.password.update'), ['current_password' => 'clave-actual-1', 'password' => 'clave-nueva-22', 'password_confirmation' => 'clave-nueva-22'])
            ->assertSessionHasNoErrors();

        $this->logout();
        $this->login('p@test.com', 'clave-nueva-22')->assertRedirect(route('dashboard'));
    }

    public function test_token_is_not_readable_in_the_queue_or_database(): void
    {
        Mail::swap(new MailManager($this->app));
        config(['queue.default' => 'database']);
        $student = $this->student('PRIMARY2A');
        $representative = $this->representative($student);

        $this->invite($student, 'representative', $representative->id)->assertSessionHasNoErrors();

        $job = DB::table('jobs')->orderByDesc('id')->value('payload');
        $this->assertStringNotContainsString('/activar/', $job);
        $this->assertStringNotContainsString('marta@test.com', $job);
        $this->assertSame(64, strlen(PortalInvitation::query()->value('token_hash')));

        $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $message = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertStringContainsString('/activar/', $message->getHtmlBody());
    }

    public function test_document_is_normalized(): void
    {
        $this->assertSame('12345678', DocumentNumber::normalize('V-12.345.678'));
        $this->assertSame('12345678', DocumentNumber::normalize(' e 12345678 '));
        $this->assertNull(DocumentNumber::normalize(' - '));
    }
}
