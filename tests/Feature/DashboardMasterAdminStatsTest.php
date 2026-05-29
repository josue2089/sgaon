<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMasterAdminStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_dashboard_counts_all_campuses(): void
    {
        $picacho = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $cascada = Campus::query()->create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $master = $this->createMasterAdmin($picacho->id);

        foreach (range(1, 9) as $i) {
            Teacher::query()->create([
                'campus_id' => $picacho->id,
                'first_name' => "Teacher{$i}",
                'last_name' => 'Picacho',
                'email' => "teacher-pic-{$i}@test.dev",
                'status' => 'active',
            ]);
            Student::query()->create([
                'campus_id' => $picacho->id,
                'first_name' => "Student{$i}",
                'last_name' => 'Picacho',
                'status' => 'active',
            ]);
        }

        foreach (range(1, 10) as $i) {
            Teacher::query()->create([
                'campus_id' => $cascada->id,
                'first_name' => "Teacher{$i}",
                'last_name' => 'Cascada',
                'email' => "teacher-cas-{$i}@test.dev",
                'status' => 'active',
            ]);
            Student::query()->create([
                'campus_id' => $cascada->id,
                'first_name' => "Student{$i}",
                'last_name' => 'Cascada',
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($master)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('teachersCount', 19);
        $response->assertViewHas('studentsCount', 19);
    }

    public function test_non_master_admin_dashboard_counts_only_own_campus(): void
    {
        $picacho = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $cascada = Campus::query()->create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $admin = User::factory()->create([
            'campus_id' => $picacho->id,
            'role' => 'admin',
            'is_master' => false,
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        Teacher::query()->create([
            'campus_id' => $picacho->id,
            'first_name' => 'Local',
            'last_name' => 'Teacher',
            'email' => 'local-teacher@test.dev',
            'status' => 'active',
        ]);
        Teacher::query()->create([
            'campus_id' => $cascada->id,
            'first_name' => 'Remote',
            'last_name' => 'Teacher',
            'email' => 'remote-teacher@test.dev',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('teachersCount', 1);
    }

    private function createMasterAdmin(int $campusId): User
    {
        $master = User::factory()->create([
            'campus_id' => $campusId,
            'role' => 'admin',
            'is_master' => true,
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $master->roles()->syncWithoutDetaching([$adminRole->id]);

        return $master;
    }
}
