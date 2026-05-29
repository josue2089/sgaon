<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRegistrationProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_form_lists_active_programs_including_custom_programs(): void
    {
        Program::query()->firstOrFail();
        Program::query()->create([
            'name' => 'Robótica',
            'code' => 'ROB-TEST',
            'status' => 'active',
            'description' => 'Mecánica y electrónica.',
        ]);

        $campus = Campus::query()->create(['name' => 'Campus A', 'code' => 'CA', 'status' => 'active']);
        $admin = User::factory()->create(['campus_id' => $campus->id, 'role' => 'admin']);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $response = $this->actingAs($admin)->get(route('students.create'));

        $response->assertOk();
        $response->assertSee('Programa de inscripción');
        $response->assertSee('Robótica');
        $response->assertSee('Pre-Primary');
    }
}
