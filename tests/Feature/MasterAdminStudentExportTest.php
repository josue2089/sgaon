<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAdminStudentExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UatFixtureSeeder::class);
    }

    public function test_master_admin_can_download_students_csv(): void
    {
        $master = $this->createMasterAdmin();
        $student = Student::where('email', 'studenta@uat.test')->firstOrFail();
        $student->update([
            'status' => 'inactive',
            'registration_program_id' => Program::query()->where('code', 'HS')->value('id'),
        ]);

        $response = $this->actingAs($master)->get(route('students.export', ['status' => 'inactive']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('ID,Sede,Nombres,Apellidos', $content);
        $this->assertStringContainsString($student->first_name, $content);
        $this->assertStringContainsString('Inactivo', $content);
        $this->assertStringContainsString('HighSchool', $content);
    }

    public function test_master_admin_export_respects_status_filter(): void
    {
        $master = $this->createMasterAdmin();
        Student::query()->update(['status' => 'inactive']);
        Student::query()->first()?->update(['status' => 'active']);

        $response = $this->actingAs($master)->get(route('students.export', ['status' => 'active']));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Activo', $content);
        $this->assertStringNotContainsString('Inactivo', $content);
    }

    public function test_non_master_admin_cannot_export_students(): void
    {
        $admin = User::where('email', 'admina@uat.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('students.export'))
            ->assertForbidden();
    }

    public function test_master_admin_sees_export_button_on_students_index(): void
    {
        $master = $this->createMasterAdmin();

        $this->actingAs($master)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Exportar CSV');
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
