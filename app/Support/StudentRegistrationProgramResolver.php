<?php

namespace App\Support;

use App\Models\Program;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentRegistrationProgramResolver
{
    /** @var Collection<int, Program>|null */
    private ?Collection $activePrograms = null;

    public function programCodeForNivel(string $nivel): ?string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($nivel))) ?? '');

        if ($code === '') {
            return null;
        }

        if (str_starts_with($code, 'ROB')) {
            return 'ROB';
        }

        if (str_starts_with($code, 'CON') || str_starts_with($code, 'HS')) {
            return 'HS';
        }

        if (str_starts_with($code, 'PREPR') || str_starts_with($code, 'PRE')) {
            return 'PREP';
        }

        if (str_starts_with($code, 'PRY') || str_starts_with($code, 'PR')) {
            return 'PRIMARY';
        }

        return null;
    }

    public function resolve(string $nivel): ?Program
    {
        $programCode = $this->programCodeForNivel($nivel);
        if ($programCode === null) {
            return null;
        }

        return $this->findProgramByBucket($programCode);
    }

    public function resolveMessage(string $nivel): string
    {
        $programCode = $this->programCodeForNivel($nivel);
        if ($programCode === null) {
            return "No se reconoce el nivel «{$nivel}».";
        }

        if (! $this->resolve($nivel)) {
            return match ($programCode) {
                'ROB' => 'No se encontró un programa activo de Robótica en la tabla programs (código o nombre compatible con ROB*).',
                'HS' => 'No se encontró el programa High School activo (código HS).',
                'PRIMARY' => 'No se encontró el programa Primary activo (código PRIMARY).',
                'PREP' => 'No se encontró el programa Pre-Primary activo (código PREP).',
                default => "No hay programa activo para el nivel «{$nivel}».",
            };
        }

        return '';
    }

    private function findProgramByBucket(string $bucketCode): ?Program
    {
        $programs = $this->activePrograms();

        $exactCodes = match ($bucketCode) {
            'HS' => ['HS', 'HIGHSCHOOL', 'HIGH_SCHOOL'],
            'PRIMARY' => ['PRIMARY'],
            'PREP' => ['PREP', 'PREPRIMARY', 'PRE_PRIMARY'],
            default => [],
        };

        foreach ($exactCodes as $code) {
            $program = $programs->first(fn (Program $p) => $this->normalizeLabel($p->code) === $this->normalizeLabel($code));
            if ($program) {
                return $program;
            }
        }

        return match ($bucketCode) {
            'ROB' => $this->findRoboticsProgram($programs),
            'HS' => $this->findByNameHints($programs, ['highschool', 'highschool', 'secundaria']),
            'PRIMARY' => $this->findByNameHints($programs, ['primary', 'primaria']),
            'PREP' => $this->findByNameHints($programs, ['preprimary', 'preescolar', 'preescolar']),
            default => null,
        };
    }

    /**
     * @param  Collection<int, Program>  $programs
     */
    private function findRoboticsProgram(Collection $programs): ?Program
    {
        return $programs->first(function (Program $program) {
            $code = $this->normalizeLabel($program->code);
            $name = $this->normalizeLabel($program->name);

            return str_starts_with($code, 'rob')
                || str_contains($name, 'robotica')
                || str_contains($name, 'robotics')
                || str_contains($name, 'robot');
        });
    }

    /**
     * @param  Collection<int, Program>  $programs
     * @param  list<string>  $hints
     */
    private function findByNameHints(Collection $programs, array $hints): ?Program
    {
        return $programs->first(function (Program $program) use ($hints) {
            $name = $this->normalizeLabel($program->name);

            foreach ($hints as $hint) {
                if (str_contains($name, $this->normalizeLabel($hint))) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @return Collection<int, Program>
     */
    private function activePrograms(): Collection
    {
        if ($this->activePrograms === null) {
            $this->activePrograms = Program::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }

        return $this->activePrograms;
    }

    private function normalizeLabel(?string $value): string
    {
        $normalized = Str::lower(Str::ascii(trim((string) $value)));

        return preg_replace('/[^a-z0-9]/', '', $normalized) ?? '';
    }
}
