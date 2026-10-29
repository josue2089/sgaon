<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\User;
use App\Support\CefrScale;
use App\Support\HistoricalProgramResolver;
use App\Support\StudentRegistrationProgramResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CefrStructureTest extends TestCase
{
    use RefreshDatabase;

    private function level(string $code): ProgramLevel
    {
        return ProgramLevel::query()->where('code', $code)->firstOrFail();
    }

    public function test_gse_ranges_follow_pearson_bands(): void
    {
        $this->assertSame([43, 50], CefrScale::gseRange('B1 Teens'));
        $this->assertSame([30, 42], CefrScale::gseRange('A2 / A2+'));
        $this->assertSame([67, 84], CefrScale::gseRange('B2+ / C1'));
        $this->assertSame([22, 35], CefrScale::gseRange('A1+ / A2 Kids'));
        $this->assertNull(CefrScale::gseRange('Inicial'));
    }

    public function test_command_has_dry_run_creates_adults_program_and_is_idempotent(): void
    {
        $this->artisan('programs:apply-cefr-structure', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();
        $this->assertNull(Program::query()->where('code', Program::ADULTS_CODE)->first());
        $this->assertNull($this->level('HS3A')->cefr_label);

        $this->artisan('programs:apply-cefr-structure')->assertSuccessful();

        $adults = Program::query()->where('code', Program::ADULTS_CODE)->firstOrFail();
        $this->assertSame('adults', $adults->audience);
        $this->assertTrue($adults->uses_cefr);
        $this->assertSame(13, $adults->levels()->count());
        $this->assertSame(20, $this->level('IU4B')->lessons()->count());
        $this->assertSame('B1 / B1+', $this->level('IU4B')->cefr_label);
        $this->assertSame('Focus 3', $this->level('IU4B')->book);

        $hs3a = $this->level('HS3A');
        $this->assertSame('B1 Teens', $hs3a->cefr_label);
        $this->assertSame([43, 50], [$hs3a->gse_min, $hs3a->gse_max]);
        $this->assertSame([3, 'A'], [$hs3a->level_number, $hs3a->part]);
        $this->assertSame('C1', $this->level('HS6B')->cefr_label);
        $this->assertSame('A1 Kids', $this->level('PRIMARY2B')->cefr_label);
        $this->assertSame([6, 'B'], [$this->level('PREPRIMARY6B')->level_number, $this->level('PREPRIMARY6B')->part]);
        $this->assertSame(11, Program::query()->where('code', 'HS')->value('age_min'));
        $this->assertTrue(AuditLog::query()->where('action', 'programs.cefr_structure_applied')->exists());

        $this->artisan('programs:apply-cefr-structure')
            ->expectsOutputToContain('Sin cambios')
            ->assertSuccessful();
        $this->assertSame(13, $adults->levels()->count());
    }

    public function test_command_keeps_manual_edits_unless_overwrite(): void
    {
        $this->level('HS3A')->update(['book' => 'Libro propio']);

        $this->artisan('programs:apply-cefr-structure')->assertSuccessful();
        $this->assertSame('Libro propio', $this->level('HS3A')->book);
        $this->assertSame('B1 Teens', $this->level('HS3A')->cefr_label);

        $this->artisan('programs:apply-cefr-structure', ['--overwrite' => true])->assertSuccessful();
        $this->assertSame('English Aware 3', $this->level('HS3A')->book);
    }

    public function test_next_level_crosses_programs_and_equivalent_matches_hs_with_adults(): void
    {
        $this->artisan('programs:apply-cefr-structure')->assertSuccessful();

        $this->assertSame('HS1A', $this->level('PRIMARY6B')->nextLevel()?->code);
        $this->assertSame('PRIMARY2A', $this->level('PRIMARY1B')->nextLevel()?->code);

        $adults = Program::query()->where('code', Program::ADULTS_CODE)->firstOrFail();
        $this->assertSame('IU4B', $this->level('HS4B')->equivalentIn($adults)?->code);
        $this->assertSame('PU6A', $this->level('HS6A')->equivalentIn($adults)?->code);
        $this->assertSame('BUPUCONV', $this->level('CONVERSATIONAL2')->equivalentIn($adults)?->code);
        $this->assertNull($this->level('CONVERSATIONAL1')->equivalentIn($adults));
    }

    public function test_admin_edits_cefr_fields_and_gse_is_suggested(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $level = $this->level('HS2A');

        $this->actingAs($master)->put(route('program-levels.update', $level), [
            'name' => 'HS2A', 'code' => 'HS2A', 'sort_order' => 3, 'program_total' => 14, 'academic_hours' => 40,
            'reminder_days_before' => 5, 'status' => 'active',
            'level_number' => 2, 'part' => 'A', 'cefr_label' => 'A2+', 'book' => 'English Aware 2',
            'next_program_level_id' => '',
        ])->assertSessionHasNoErrors();

        $level->refresh();
        $this->assertSame('A2+', $level->cefr_label);
        $this->assertSame([36, 42], [$level->gse_min, $level->gse_max]);
        $this->assertSame('CEFR A2+ · GSE 36–42', $level->cefrSummary());

        $this->actingAs($master)->get(route('programs.show', $level->program_id))
            ->assertOk()
            ->assertSee('GSE 36–42')
            ->assertSee('English Aware 2');
        $this->actingAs($master)->get(route('program-levels.edit', $level))
            ->assertOk()
            ->assertSee('Equivalencia CEFR');
    }

    public function test_resolvers_send_bu_iu_pu_to_adults_program(): void
    {
        $this->artisan('programs:apply-cefr-structure')->assertSuccessful();
        $adultsId = Program::query()->where('code', Program::ADULTS_CODE)->value('id');

        $historical = new HistoricalProgramResolver;
        $this->assertSame($adultsId, $historical->resolve('BU 1A'));
        $this->assertSame($adultsId, $historical->resolve('PU6B'));
        $this->assertSame(Program::query()->where('code', 'HS')->value('id'), $historical->resolve('HS3A'));

        $registration = new StudentRegistrationProgramResolver;
        $this->assertSame($adultsId, $registration->resolve('IU 3B')?->id);
        $this->assertSame('PRIMARY', $registration->programCodeForNivel('Primary 2A'));
    }
}
