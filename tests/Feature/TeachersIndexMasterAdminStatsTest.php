<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachersIndexMasterAdminStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_teachers_index_counts_all_campuses_and_students(): void
    {
        $picacho = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $cascada = Campus::query()->create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $master = $this->createMasterAdmin($picacho->id);

        foreach (range(1, 3) as $i) {
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

        foreach (range(1, 2) as $i) {
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
                'status' => 'inactive',
            ]);
        }

        $response = $this->actingAs($master)->get(route('teachers.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary) => $summary['total'] === 5 && $summary['students_total'] === 5);
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
