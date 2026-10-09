<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramExtracurricularTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);

        return User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
    }

    public function test_existing_programs_are_not_extracurricular(): void
    {
        $this->assertTrue(Program::query()->exists());
        $this->assertFalse(Program::query()->where('is_extracurricular', true)->exists());
    }

    public function test_master_can_create_and_toggle_extracurricular_program(): void
    {
        $master = $this->master();

        $this->actingAs($master)
            ->post(route('programs.store'), [
                'name' => 'Danza',
                'code' => 'DANZA',
                'status' => 'active',
                'is_extracurricular' => '1',
            ])
            ->assertRedirect();

        $program = Program::query()->where('code', 'DANZA')->firstOrFail();
        $this->assertTrue($program->is_extracurricular);

        $this->actingAs($master)
            ->put(route('programs.update', $program), [
                'name' => 'Danza',
                'code' => 'DANZA',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertFalse($program->fresh()->is_extracurricular);
    }

    public function test_index_shows_badge_and_filters_by_type(): void
    {
        $master = $this->master();
        Program::create(['name' => 'Fútbol', 'code' => 'FUT', 'status' => 'active', 'is_extracurricular' => true]);
        $regular = Program::query()->where('is_extracurricular', false)->firstOrFail();

        $this->actingAs($master)->get(route('programs.index'))
            ->assertOk()
            ->assertSee('Fútbol')
            ->assertSee('<span class="badge-pill badge-info">Extracurricular</span>', false);

        $this->actingAs($master)->get(route('programs.index', ['type' => 'extracurricular']))
            ->assertOk()
            ->assertSee('Fútbol')
            ->assertDontSee($regular->name);

        $this->actingAs($master)->get(route('programs.index', ['type' => 'regular']))
            ->assertOk()
            ->assertDontSee('Fútbol');
    }
}
