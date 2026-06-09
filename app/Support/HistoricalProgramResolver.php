<?php

namespace App\Support;

use App\Models\Program;
use Illuminate\Support\Str;

class HistoricalProgramResolver
{
    /** @var array<string, int>|null */
    private ?array $programIds = null;

    public function resolve(?string $levelCode): ?int
    {
        if ($levelCode === null || trim($levelCode) === '') {
            return null;
        }

        $normalized = Str::upper(Str::ascii(trim($levelCode)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if ($this->matchesPrePrimary($normalized)) {
            return $this->programId('Pre-Primary');
        }

        if ($this->matchesPrimary($normalized)) {
            return $this->programId('Primary');
        }

        if ($this->matchesHighSchool($normalized)) {
            return $this->programId('HighSchool');
        }

        if ($this->matchesRobotics($normalized)) {
            return $this->programId('Robótica');
        }

        return null;
    }

    private function matchesPrePrimary(string $code): bool
    {
        return str_contains($code, 'PRE-PRIMARY')
            || str_contains($code, 'PRE PRIMARY')
            || str_contains($code, 'PREP')
            || str_starts_with($code, 'INTRO')
            || str_starts_with($code, 'IC-')
            || str_starts_with($code, 'IC ');
    }

    private function matchesPrimary(string $code): bool
    {
        return str_starts_with($code, 'PRIMARY')
            || str_starts_with($code, 'BP')
            || str_starts_with($code, 'PASS')
            || str_starts_with($code, 'BU')
            || str_contains($code, 'BASIC USER');
    }

    private function matchesHighSchool(string $code): bool
    {
        return str_starts_with($code, 'HS')
            || str_contains($code, 'HSB')
            || str_contains($code, 'HIGH SCHOOL');
    }

    private function matchesRobotics(string $code): bool
    {
        return str_contains($code, 'ROBOT');
    }

    private function programId(string $name): ?int
    {
        $this->loadPrograms();

        return $this->programIds[$name] ?? null;
    }

    private function loadPrograms(): void
    {
        if ($this->programIds !== null) {
            return;
        }

        $this->programIds = [];
        foreach (Program::query()->get(['id', 'name']) as $program) {
            $this->programIds[$program->name] = $program->id;
        }
    }
}
