<?php

namespace App\Support;

use App\Models\Program;
use Illuminate\Support\Collection;

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

        $programs = $this->activePrograms();

        if ($programCode === 'ROB') {
            return $programs->first(function (Program $program) {
                $normalizedCode = $this->normalizeLabel($program->code);
                $normalizedName = $this->normalizeLabel($program->name);

                return $normalizedCode === 'rob'
                    || str_contains($normalizedName, 'robotica')
                    || str_contains($normalizedName, 'robotics');
            });
        }

        $program = $programs->firstWhere('code', $programCode);
        if ($program) {
            return $program;
        }

        return $programs->first(fn (Program $p) => $this->normalizeLabel($p->code) === $this->normalizeLabel($programCode));
    }

    public function resolveMessage(string $nivel): string
    {
        $programCode = $this->programCodeForNivel($nivel);
        if ($programCode === null) {
            return "No se reconoce el nivel «{$nivel}».";
        }

        if ($programCode === 'ROB' && ! $this->resolve($nivel)) {
            return 'El programa Robótica no existe o no está activo. Créelo en Programas antes de importar filas ROB*.';
        }

        if (! $this->resolve($nivel)) {
            return "No hay programa activo con código «{$programCode}» para el nivel «{$nivel}».";
        }

        return '';
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
        return strtolower(preg_replace('/[^a-z0-9]/', '', (string) $value) ?? '');
    }
}
