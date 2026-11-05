<?php

namespace Tests\Feature;

use App\Mail\PortalInvitationMail;
use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\ChargePaymentRequest;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\PortalInvitation;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use App\Services\RepresentativeMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PortalBulkAccessTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Campus $other;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->artisan('programs:apply-cefr-structure')->assertSuccessful();
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->other = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
    }

    private function student(string $name, string $levelCode = 'PRIMARY2A', ?Campus $campus = null, array $attributes = []): Student
    {
        $campus ??= $this->campus;
        $level = ProgramLevel::query()->where('code', $levelCode)->firstOrFail();
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $campus->id, 'name' => 'Nivel'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => 'Curso '.$name,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $campus->id, 'course_id' => $course->id, 'name' => 'G '.$name, 'status' => 'active', 'capacity' => 20]);
        $student = Student::create($attributes + ['campus_id' => $campus->id, 'first_name' => $name, 'last_name' => 'Gil', 'status' => 'active']);
        Enrollment::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);

        return $student;
    }

    private function representative(Student $student, array $attributes = []): Representative
    {
        $representative = Representative::create($attributes + ['campus_id' => $student->campus_id, 'first_name' => 'Marta', 'last_name' => 'Gil', 'email' => 'marta@test.com', 'document_id' => 'V-11.222.333']);
        $representative->students()->attach($student->id);

        return $representative;
    }

    public function test_merge_has_dry_run_keeps_one_record_and_reports_doubts(): void
    {
        $first = $this->student('Ana');
        $second = $this->student('Luis');
        $third = $this->student('Nora');
        $a = $this->representative($first, ['phone' => null]);
        $b = $this->representative($second, ['document_id' => '11222333', 'first_name' => 'MARTA', 'phone' => '0414', 'email' => null]);
        $c = $this->representative($third, ['document_id' => null, 'email' => 'MARTA@test.com ']);
        // Misma cédula, otra persona: se reporta y no se une.
        $different = $this->representative($this->student('Eva'), ['first_name' => 'Jose', 'last_name' => 'Rojas', 'email' => null]);
        // Correo compartido, otra persona: no se une.
        $partner = $this->representative($this->student('Leo'), ['first_name' => 'Pedro', 'last_name' => 'Gil', 'document_id' => '55555555']);
        $request = ChargePaymentRequest::create(['campus_id' => $this->campus->id, 'student_id' => $second->id, 'charge_id' => Charge::create(['campus_id' => $this->campus->id, 'student_id' => $second->id, 'concept' => 'X', 'charge_type' => 'tuition', 'amount' => 10, 'currency' => 'USD', 'due_date' => today(), 'status' => 'pending'])->id, 'representative_id' => $b->id, 'amount' => 10, 'currency' => 'USD', 'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION, 'submitted_at' => now(), 'proof_path' => 'x.jpg']);

        $this->artisan('representatives:merge-duplicates', ['--dry-run' => true])
            ->expectsOutputToContain('Se unirían 1 grupos (2 fichas menos)')
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();
        $this->assertSame(5, Representative::query()->count());

        $this->artisan('representatives:merge-duplicates')->assertSuccessful();

        $this->assertSame(3, Representative::query()->count());
        $survivor = $a->fresh();
        $this->assertSame('0414', $survivor->phone);
        $this->assertEqualsCanonicalizing([$first->id, $second->id, $third->id], $survivor->students()->pluck('students.id')->all());
        $this->assertSame($survivor->id, $request->fresh()->representative_id);
        $this->assertNull($b->fresh());
        $this->assertNull($c->fresh());
        $this->assertNotNull($different->fresh());
        $this->assertNotNull($partner->fresh());
        $audit = AuditLog::query()->where('action', 'representatives.merged')->sole();
        $this->assertCount(2, $audit->payload['merged']);
        $this->assertSame([], (new RepresentativeMerger)->plan()['merge']);
    }

    public function test_merge_skips_records_with_two_user_accounts(): void
    {
        $a = $this->representative($this->student('Ana'), ['user_id' => User::factory()->create(['role' => 'representative'])->id]);
        $b = $this->representative($this->student('Luis'), ['user_id' => User::factory()->create(['role' => 'representative'])->id]);

        $this->artisan('representatives:merge-duplicates')->expectsOutputToContain('Se unieron 0 grupos')->assertSuccessful();

        $this->assertNotNull($a->fresh());
        $this->assertNotNull($b->fresh());
    }

    public function test_siblings_share_one_representative_when_saving_the_form(): void
    {
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $base = fn (string $first) => [
            'campus_id' => $this->campus->id, 'first_name' => $first, 'last_name' => 'Gil', 'status' => 'active', 'enrollment_date' => '2026-09-01',
            'representative' => ['first_name' => 'Marta', 'last_name' => 'Gil', 'document_id' => 'V-11.222.333', 'email' => 'marta@test.com'],
        ];

        $this->actingAs($master)->post(route('students.store'), $base('Ana'))->assertSessionHasNoErrors();
        $this->actingAs($master)->post(route('students.store'), $base('Luis') + ['x' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, Representative::query()->count());
        $representative = Representative::query()->sole();
        $this->assertSame(2, $representative->students()->count());

        // Cambiar el representante de un hermano no le cambia los datos al otro.
        $luis = Student::query()->where('first_name', 'Luis')->sole();
        $form = $base('Luis');
        $form['representative'] = ['first_name' => 'Pedro', 'last_name' => 'Rojas', 'document_id' => '44444444', 'email' => 'pedro@test.com'];
        $this->actingAs($master)->put(route('students.update', $luis), $form)->assertSessionHasNoErrors();

        $this->assertSame('Marta', $representative->fresh()->first_name);
        $this->assertSame('Pedro', $luis->fresh()->representatives()->sole()->first_name);
        $this->assertSame(1, $representative->fresh()->students()->count());
    }

    public function test_representative_with_two_children_has_one_account_and_sees_both(): void
    {
        $ana = $this->student('Ana');
        $luis = $this->student('Luis');
        $representative = $this->representative($ana);
        $representative->students()->attach($luis->id);

        $this->actingAs($this->admin)->post(route('portal-access.invite'), ['people' => ['representative:'.$representative->id]])
            ->assertSessionHas('success', 'Invitaciones enviadas: 1.');
        $this->assertSame(1, User::query()->where('role', 'representative')->count());

        $user = $representative->fresh()->user;
        $this->actingAs($user)->get(route('portal.representative'))->assertOk()->assertSee('Ana Gil')->assertSee('Luis Gil');
    }

    public function test_account_shared_by_two_records_sees_all_children(): void
    {
        $ana = $this->student('Ana');
        $luis = $this->student('Luis');
        $mother = $this->representative($ana);
        $father = $this->representative($luis, ['first_name' => 'Pedro', 'document_id' => '55555555']);

        $this->actingAs($this->admin)->post(route('portal-access.invite'), ['people' => ['representative:'.$mother->id, 'representative:'.$father->id]])->assertSessionHas('success');
        $this->assertSame($mother->fresh()->user_id, $father->fresh()->user_id);

        $this->actingAs($mother->fresh()->user)->get(route('portal.representative'))->assertOk()->assertSee('Ana Gil')->assertSee('Luis Gil');
    }

    public function test_screen_lists_own_campus_filters_and_explains_blockers(): void
    {
        $ana = $this->student('Ana');
        $this->representative($ana);
        $noEmail = $this->student('Sinco');
        $this->representative($noEmail, ['first_name' => 'Carla', 'email' => null, 'document_id' => '77777777']);
        $this->student('Teen', 'HS3A');
        $this->representative($this->student('Fuera', 'PRIMARY2A', $this->other), ['first_name' => 'Externa', 'email' => 'ext@test.com', 'document_id' => '88888888']);

        $this->actingAs($this->admin)->get(route('portal-access.index'))
            ->assertOk()
            ->assertSee('Marta Gil')
            ->assertSee('Ana Gil')
            ->assertSee('El representante no tiene correo.')
            ->assertSee('Falta un correo')
            ->assertDontSee('Externa')
            ->assertDontSee('entra por el portal del representante.</td>', false);

        $hs = ProgramLevel::query()->where('code', 'HS3A')->value('program_id');
        $this->actingAs($this->admin)->get(route('portal-access.index', ['program_id' => $hs]))
            ->assertOk()
            ->assertSee('Teen Gil')
            ->assertDontSee('Marta Gil');
        $this->actingAs($this->admin)->get(route('portal-access.index', ['state' => 'active']))
            ->assertOk()
            ->assertDontSee('Marta Gil');
    }

    public function test_bulk_invite_respects_campus_and_reports_who_failed(): void
    {
        $marta = $this->representative($this->student('Ana'));
        $noEmail = $this->representative($this->student('Sinco'), ['first_name' => 'Carla', 'email' => null, 'document_id' => '77777777']);
        $external = $this->representative($this->student('Fuera', 'PRIMARY2A', $this->other), ['first_name' => 'Externa', 'email' => 'ext@test.com', 'document_id' => '88888888']);

        $this->actingAs($this->admin)->post(route('portal-access.invite'), ['people' => ['representative:'.$external->id]])->assertForbidden();
        $this->assertSame(0, PortalInvitation::query()->count());

        $this->actingAs($this->admin)->post(route('portal-access.invite'), ['people' => ['representative:'.$marta->id, 'representative:'.$noEmail->id]])
            ->assertSessionHas('success', 'Invitaciones enviadas: 1.')
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'Carla Gil') && str_contains($message, 'no tiene correo'));
        Mail::assertQueued(PortalInvitationMail::class, 1);
    }

    public function test_command_has_dry_run_and_needs_a_scope(): void
    {
        $marta = $this->representative($this->student('Ana'));
        $this->representative($this->student('Sinco'), ['first_name' => 'Carla', 'email' => null, 'document_id' => '77777777']);
        $this->representative($this->student('Fuera', 'PRIMARY2A', $this->other), ['first_name' => 'Externa', 'email' => 'ext@test.com', 'document_id' => '88888888']);

        $this->artisan('portal:invite')->assertFailed();

        $this->artisan('portal:invite', ['--campus' => $this->campus->id, '--dry-run' => true])
            ->expectsOutputToContain('Personas a invitar: 1.')
            ->expectsOutputToContain('1 no se pueden invitar')
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();
        Mail::assertNothingOutgoing();

        $this->artisan('portal:invite', ['--campus' => $this->campus->id, '--force' => true])
            ->expectsOutputToContain('Invitaciones enviadas: 1.')
            ->assertSuccessful();
        $this->assertSame('marta@test.com', PortalInvitation::query()->sole()->sent_to);
        $this->assertNotNull($marta->fresh()->user_id);

        // Una segunda corrida no vuelve a escribir a quien ya tiene invitación vigente.
        $this->artisan('portal:invite', ['--campus' => $this->campus->id, '--force' => true])
            ->expectsOutputToContain('Personas a invitar: 0.')
            ->assertSuccessful();
        Mail::assertQueued(PortalInvitationMail::class, 1);
    }

    public function test_command_does_not_reactivate_deactivated_accounts(): void
    {
        $marta = $this->representative($this->student('Ana'));
        $this->artisan('portal:invite', ['--campus' => $this->campus->id, '--force' => true])->assertSuccessful();
        $marta->fresh()->user->forceFill(['status' => 'inactive'])->save();

        $this->artisan('portal:invite', ['--campus' => $this->campus->id, '--force' => true, '--resend' => true])
            ->expectsOutputToContain('Personas a invitar: 0.')
            ->assertSuccessful();
        $this->assertSame('inactive', $marta->fresh()->user->status);
    }
}
