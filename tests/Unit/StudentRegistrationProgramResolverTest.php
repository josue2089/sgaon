<?php

namespace Tests\Unit;

use App\Models\Program;
use App\Support\StudentRegistrationProgramResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRegistrationProgramResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_robotics_program_with_accented_name(): void
    {
        Program::query()->where('code', 'HS')->firstOrFail();

        $robotics = Program::query()->create([
            'name' => 'Robótica',
            'code' => 'ROB',
            'status' => 'active',
            'description' => 'Programa de robótica',
        ]);

        $resolved = (new StudentRegistrationProgramResolver)->resolve('ROB6');

        $this->assertNotNull($resolved);
        $this->assertSame($robotics->id, $resolved->id);
    }

    public function test_resolves_robotics_program_with_non_standard_code(): void
    {
        Program::query()->where('code', 'HS')->firstOrFail();

        $robotics = Program::query()->create([
            'name' => 'Robótica',
            'code' => 'ROB-TEST',
            'status' => 'active',
            'description' => null,
        ]);

        $resolved = (new StudentRegistrationProgramResolver)->resolve('ROB6');

        $this->assertNotNull($resolved);
        $this->assertSame($robotics->id, $resolved->id);
    }

    public function test_resolves_hs_and_primary_by_standard_codes(): void
    {
        $resolver = new StudentRegistrationProgramResolver;

        $hs = $resolver->resolve('HS3B');
        $primary = $resolver->resolve('PR2B');

        $this->assertSame('HS', $hs?->code);
        $this->assertSame('PRIMARY', $primary?->code);
    }
}
