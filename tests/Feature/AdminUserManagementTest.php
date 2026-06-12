<?php

namespace Tests\Feature;

use App\Mail\AdminCredentialsMail;
use App\Models\Campus;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_create_campus_admin_and_email_is_sent(): void
    {
        Mail::fake();

        [$master, $picacho] = $this->masterAndCampuses();

        $response = $this->actingAs($master)->post(route('admin-users.store'), [
            'name' => 'Admin Picacho',
            'email' => 'infosaaoe@gmail.com',
            'phone' => '04120000000',
            'status' => 'active',
            'access_mode' => 'selected',
            'campus_ids' => [$picacho->id],
        ]);

        $response->assertRedirect(route('admin-users.index'));
        $response->assertSessionHas('success');

        $user = User::query()->where('email', 'infosaaoe@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_master);
        $this->assertSame($picacho->id, (int) $user->campus_id);
        $this->assertSame([$picacho->id], $user->campuses()->pluck('campuses.id')->all());

        Mail::assertSent(AdminCredentialsMail::class, fn (AdminCredentialsMail $mail) => $mail->hasTo('infosaaoe@gmail.com'));
    }

    public function test_non_master_admin_cannot_access_admin_users_module(): void
    {
        [$master, $picacho] = $this->masterAndCampuses();
        $campusAdmin = $this->createCampusAdmin($picacho);

        $this->actingAs($campusAdmin)->get(route('admin-users.index'))->assertForbidden();
        $this->actingAs($campusAdmin)->get(route('admin-users.create'))->assertForbidden();
    }

    public function test_campus_admin_only_sees_students_from_assigned_campus(): void
    {
        [$master, $picacho, $cascada] = $this->masterAndCampuses();

        $picachoStudent = Student::query()->create([
            'campus_id' => $picacho->id,
            'first_name' => 'Alumno',
            'last_name' => 'Picacho',
            'email' => 'picacho-student@test.dev',
            'status' => 'active',
        ]);

        $cascadaStudent = Student::query()->create([
            'campus_id' => $cascada->id,
            'first_name' => 'Alumno',
            'last_name' => 'Cascada',
            'email' => 'cascada-student@test.dev',
            'status' => 'active',
        ]);

        $picachoAdmin = $this->createCampusAdmin($picacho);

        $response = $this->actingAs($picachoAdmin)->get(route('students.index'));

        $response->assertOk();
        $response->assertSee($picachoStudent->full_name);
        $response->assertDontSee($cascadaStudent->full_name);
    }

    public function test_admin_with_all_campuses_sees_every_campus_students(): void
    {
        [$master, $picacho, $cascada] = $this->masterAndCampuses();

        Student::query()->create([
            'campus_id' => $picacho->id,
            'first_name' => 'Uno',
            'last_name' => 'Picacho',
            'email' => 'all-picacho@test.dev',
            'status' => 'active',
        ]);

        Student::query()->create([
            'campus_id' => $cascada->id,
            'first_name' => 'Dos',
            'last_name' => 'Cascada',
            'email' => 'all-cascada@test.dev',
            'status' => 'active',
        ]);

        $allCampusAdmin = User::factory()->create([
            'campus_id' => null,
            'role' => 'admin',
            'is_master' => false,
            'access_all_campuses' => true,
            'email' => 'all-campus-admin@test.dev',
            'status' => 'active',
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $allCampusAdmin->roles()->syncWithoutDetaching([$adminRole->id]);

        $response = $this->actingAs($allCampusAdmin)->get(route('students.index'));

        $response->assertOk();
        $response->assertSee('Uno Picacho');
        $response->assertSee('Dos Cascada');
    }

    public function test_master_admin_sees_admin_users_nav_link(): void
    {
        [$master] = $this->masterAndCampuses();

        $response = $this->actingAs($master)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Usuarios admin');
    }

    public function test_cannot_edit_master_user_via_admin_users_module(): void
    {
        [$master] = $this->masterAndCampuses();

        $this->actingAs($master)->get(route('admin-users.edit', $master))->assertNotFound();
    }

    public function test_master_admin_can_edit_campus_admin(): void
    {
        [$master, $picacho] = $this->masterAndCampuses();
        $campusAdmin = $this->createCampusAdmin($picacho);

        $response = $this->actingAs($master)->get(route('admin-users.edit', $campusAdmin));

        $response->assertOk();
        $response->assertSee($campusAdmin->email);
    }

    public function test_master_admin_can_delete_campus_admin(): void
    {
        [$master, $picacho] = $this->masterAndCampuses();
        $campusAdmin = $this->createCampusAdmin($picacho);

        $response = $this->actingAs($master)->delete(route('admin-users.destroy', $campusAdmin));

        $response->assertRedirect(route('admin-users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $campusAdmin->id]);
    }

    /**
     * @return array{0: User, 1: Campus, 2?: Campus}
     */
    private function masterAndCampuses(): array
    {
        $picacho = Campus::query()->firstOrCreate(
            ['code' => 'PIC'],
            ['name' => 'Picacho', 'status' => 'active']
        );
        $cascada = Campus::query()->firstOrCreate(
            ['code' => 'CAS'],
            ['name' => 'Cascada', 'status' => 'active']
        );

        $master = User::factory()->create([
            'campus_id' => $picacho->id,
            'role' => 'admin',
            'is_master' => true,
            'email' => 'master-admin@test.dev',
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $master->roles()->syncWithoutDetaching([$adminRole->id]);

        return [$master, $picacho, $cascada];
    }

    private function createCampusAdmin(Campus $campus): User
    {
        $user = User::factory()->create([
            'campus_id' => $campus->id,
            'role' => 'admin',
            'is_master' => false,
            'access_all_campuses' => false,
            'email' => 'campus-admin-'.$campus->code.'@test.dev',
            'status' => 'active',
        ]);
        $user->campuses()->sync([$campus->id]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return $user;
    }
}
