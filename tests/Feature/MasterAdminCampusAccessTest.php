<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Period;
use App\Models\Role;
use App\Models\ScheduleTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAdminCampusAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_edit_schedule_from_other_campus(): void
    {
        $picacho = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $cascada = Campus::query()->create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $master = $this->createMasterAdmin($picacho->id);

        $schedule = ScheduleTemplate::query()->create([
            'campus_id' => $cascada->id,
            'days' => ['mon', 'wed'],
            'starts_at' => '19:20:00',
            'ends_at' => '20:20:00',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->get(route('schedules.edit', $schedule))
            ->assertOk();
    }

    public function test_master_admin_can_update_schedule_status_with_db_time_format(): void
    {
        $picacho = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $master = $this->createMasterAdmin($picacho->id);

        $schedule = ScheduleTemplate::query()->create([
            'campus_id' => $picacho->id,
            'days' => ['tue', 'thu'],
            'starts_at' => '16:00:00',
            'ends_at' => '17:30:00',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->put(route('schedules.update', $schedule), [
                'campus_id' => $picacho->id,
                'days' => ['tue', 'thu'],
                'starts_at' => '16:00:00',
                'ends_at' => '17:30:00',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('schedules.index'));

        $this->assertSame('inactive', $schedule->fresh()->status);
    }

    public function test_master_admin_can_edit_period_from_other_campus(): void
    {
        $picacho = Campus::query()->create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $cascada = Campus::query()->create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $master = $this->createMasterAdmin($picacho->id);

        $period = Period::query()->create([
            'campus_id' => $cascada->id,
            'code' => '2026-Q1',
            'description' => 'Primer trimestre',
            'status' => 'active',
        ]);

        $this->actingAs($master)
            ->get(route('periods.edit', $period))
            ->assertOk();
    }

    public function test_non_master_admin_cannot_edit_other_campus_schedule(): void
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

        $schedule = ScheduleTemplate::query()->create([
            'campus_id' => $cascada->id,
            'days' => ['mon'],
            'starts_at' => '14:20:00',
            'ends_at' => '15:50:00',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('schedules.edit', $schedule))
            ->assertForbidden();
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
