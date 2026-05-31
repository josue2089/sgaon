<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAdminStudentDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UatFixtureSeeder::class);
    }

    public function test_master_admin_can_delete_student_from_index(): void
    {
        $master = $this->createMasterAdmin();
        $student = Student::where('email', 'studenta@uat.test')->firstOrFail();

        $response = $this->actingAs($master)->delete(route('students.destroy', $student));

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_non_master_admin_cannot_delete_student(): void
    {
        $admin = User::where('email', 'admina@uat.test')->firstOrFail();
        $student = Student::where('email', 'studenta@uat.test')->firstOrFail();

        $response = $this->actingAs($admin)->delete(route('students.destroy', $student));

        $response->assertForbidden();
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_master_admin_sees_delete_action_on_student_index(): void
    {
        $master = $this->createMasterAdmin();

        $response = $this->actingAs($master)->get(route('students.index'));

        $response->assertOk();
        $response->assertSee('Eliminar');
        $response->assertSee('Eliminar permanentemente');
        $response->assertSee('Se borrará toda su información en el sistema');
    }

    private function createMasterAdmin(): User
    {
        $campus = Campus::query()->firstOrFail();
        $master = User::factory()->create([
            'campus_id' => $campus->id,
            'role' => 'admin',
            'is_master' => true,
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $master->roles()->syncWithoutDetaching([$adminRole->id]);

        return $master;
    }
}
