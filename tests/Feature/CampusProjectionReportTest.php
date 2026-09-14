<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Role;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusProjectionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_sees_projection_with_default_fee(): void
    {
        [$master, $picacho, $cascada] = $this->seedTwoCampusesWithEnrollments();

        $response = $this->actingAs($master)->get(route('reports.campus-projection'));
        $response->assertOk();
        $response->assertSee($picacho->name);
        $response->assertSee($cascada->name);
        $response->assertViewHas('fee', 80.0);
        $response->assertViewHas('totalEnrollments', 4);
        $response->assertViewHas('totalMonthly', 320.0);
        $response->assertViewHas('totalAnnual', 3200.0);
    }

    public function test_projection_uses_configured_fee(): void
    {
        [$master] = $this->seedTwoCampusesWithEnrollments();
        SystemSetting::putValue('billing.monthly_fee_usd', '95');

        $response = $this->actingAs($master)->get(route('reports.campus-projection'));
        $response->assertOk();
        $response->assertViewHas('fee', 95.0);
        $response->assertViewHas('totalMonthly', 4 * 95.0);
    }

    public function test_non_master_admin_gets_403(): void
    {
        $campus = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $admin = User::factory()->create([
            'campus_id' => $campus->id,
            'role' => 'admin',
            'is_master' => false,
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->actingAs($admin)
            ->get(route('reports.campus-projection'))
            ->assertForbidden();
    }

    public function test_settings_billing_update_persists_fee(): void
    {
        $campus = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $master = $this->makeMaster($campus);

        $this->actingAs($master)
            ->put(route('settings.billing.update'), ['fee_usd' => '120'])
            ->assertRedirect();

        $this->assertSame('120', SystemSetting::getValue('billing.monthly_fee_usd'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.billing.update']);
    }

    /**
     * @return array{0: User, 1: Campus, 2: Campus}
     */
    private function seedTwoCampusesWithEnrollments(): array
    {
        $picacho = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $cascada = Campus::query()->create(['name' => 'La Cascada', 'code' => 'CAS', 'status' => 'active']);
        $master = $this->makeMaster($picacho);

        $this->makeEnrollments($picacho, activeCount: 3, inactiveCount: 1);
        $this->makeEnrollments($cascada, activeCount: 1, inactiveCount: 0);

        return [$master, $picacho, $cascada];
    }

    private function makeMaster(Campus $campus): User
    {
        $master = User::factory()->create([
            'campus_id' => $campus->id,
            'role' => 'admin',
            'is_master' => true,
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $master->roles()->syncWithoutDetaching([$adminRole->id]);

        return $master;
    }

    private function makeEnrollments(Campus $campus, int $activeCount, int $inactiveCount): void
    {
        $level = AcademicLevel::query()->create(['campus_id' => $campus->id, 'name' => 'Primary '.$campus->code]);
        $course = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $level->id,
            'name' => 'Curso '.$campus->code,
            'status' => 'active',
        ]);
        $group = Group::query()->create([
            'campus_id' => $campus->id,
            'course_id' => $course->id,
            'name' => 'Grupo '.$campus->code,
            'status' => 'active',
            'capacity' => 30,
        ]);

        foreach (range(1, max($activeCount + $inactiveCount, 0)) as $i) {
            $student = Student::query()->create([
                'campus_id' => $campus->id,
                'first_name' => "Alumno-{$campus->code}-{$i}",
                'last_name' => 'Test',
                'email' => "alumno-{$campus->code}-{$i}@test.dev",
                'status' => 'active',
            ]);

            Enrollment::query()->create([
                'campus_id' => $campus->id,
                'student_id' => $student->id,
                'group_id' => $group->id,
                'enrolled_at' => now()->toDateString(),
                'status' => $i <= $activeCount ? 'active' : 'inactive',
                'progress' => 0,
            ]);
        }
    }
}
