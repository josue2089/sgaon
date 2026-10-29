<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Support\CefrScale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * #469: carga la estructura académica de la guía del cliente (CEFR/GSE, libros, niveles A/B, edades)
 * y crea el programa de adultos "Basic User – Prof. Use".
 *
 * Por defecto solo completa campos vacíos, así no pisa lo que el admin haya editado a mano;
 * con --overwrite reemplaza con los valores de la guía. Se puede correr varias veces.
 */
class ApplyCefrStructure extends Command
{
    protected $signature = 'programs:apply-cefr-structure
        {--dry-run : Muestra qué cambiaría sin guardar}
        {--overwrite : Reemplaza también los campos que ya tienen valor}';

    protected $description = 'Carga equivalencias CEFR/GSE de la guía y crea el programa de adultos';

    /** programa => [audience, age_min, age_max] */
    private const PROGRAMS = [
        'PREP' => ['kids', 3, 5],
        'PRIMARY' => ['kids', 6, 10],
        'HS' => ['teens', 11, 14],
        Program::ADULTS_CODE => ['adults', 14, null],
    ];

    /** código del nivel => [nivel, parte, CEFR, libro] */
    private const LEVELS = [
        'PRIMARY' => [
            'PRIMARY1A' => [1, 'A', 'A1 Kids', 'Clues All Around 1'], 'PRIMARY1B' => [1, 'B', 'A1 Kids', 'Clues All Around 1'],
            'PRIMARY2A' => [2, 'A', 'A1 Kids', 'Clues All Around 2'], 'PRIMARY2B' => [2, 'B', 'A1 Kids', 'Clues All Around 2'],
            'PRIMARY3A' => [3, 'A', 'A1+ / A2 Kids', 'Clues All Around 3'], 'PRIMARY3B' => [3, 'B', 'A1+ / A2 Kids', 'Clues All Around 3'],
            'PRIMARY4A' => [4, 'A', 'A1+ / A2 Kids', 'Clues All Around 4'], 'PRIMARY4B' => [4, 'B', 'A1+ / A2 Kids', 'Clues All Around 4'],
            'PRIMARY5A' => [5, 'A', 'A2+ Kids', 'Clues All Around 5'], 'PRIMARY5B' => [5, 'B', 'A2+ Kids', 'Clues All Around 5'],
            'PRIMARY6A' => [6, 'A', 'A2+ Kids', 'Clues All Around 6'], 'PRIMARY6B' => [6, 'B', 'A2+ Kids', 'Clues All Around 6'],
        ],
        'HS' => [
            'HS1A' => [1, 'A', 'A1', null], 'HS1B' => [1, 'B', 'A1', null],
            'HS2A' => [2, 'A', 'A1+', null], 'HS2B' => [2, 'B', 'A2', null],
            'HS3A' => [3, 'A', 'B1 Teens', 'English Aware 3'], 'HS3B' => [3, 'B', 'B1 Teens', 'English Aware 3'],
            'HS4A' => [4, 'A', 'B1+ Teens', 'English Aware 4'], 'HS4B' => [4, 'B', 'B1+ Teens', 'English Aware 4'],
            'CONVERSATIONAL1' => [null, null, 'B2 - B2+ Teens', 'English Aware 5'],
            'HS5A' => [5, 'A', 'B2', null], 'HS5B' => [5, 'B', 'B2', null],
            'HS6A' => [6, 'A', 'B2+', null], 'HS6B' => [6, 'B', 'C1', null],
            'CONVERSATIONAL2' => [7, null, 'B2 - B2+', null],
        ],
        Program::ADULTS_CODE => [
            'BU1A' => [1, 'A', 'A1', 'English Aware 1'], 'BU1B' => [1, 'B', 'A1', 'English Aware 1'],
            'BU2A' => [2, 'A', 'A2 / A2+', 'Focus 1'], 'BU2B' => [2, 'B', 'A2 / A2+', 'Focus 1'],
            'IU3A' => [3, 'A', 'A2+ / B1', 'Focus 2'], 'IU3B' => [3, 'B', 'A2+ / B1', 'Focus 2'],
            'IU4A' => [4, 'A', 'B1 / B1+', 'Focus 3'], 'IU4B' => [4, 'B', 'B1 / B1+', 'Focus 3'],
            'PU5A' => [5, 'A', 'B2 / B2+', 'English Aware 1'], 'PU5B' => [5, 'B', 'B2 / B2+', 'English Aware 1'],
            'PU6A' => [6, 'A', 'B2+ / C1', 'Focus 1'], 'PU6B' => [6, 'B', 'B2+ / C1', 'Focus 1'],
            'BUPUCONV' => [7, null, 'B2 / B2+', null],
        ],
    ];

    /** Nombres de los niveles de adultos que se crean si no existen. */
    private const ADULT_NAMES = [
        'BU1A' => 'BU 1A', 'BU1B' => 'BU 1B', 'BU2A' => 'BU 2A', 'BU2B' => 'BU 2B',
        'IU3A' => 'IU 3A', 'IU3B' => 'IU 3B', 'IU4A' => 'IU 4A', 'IU4B' => 'IU 4B',
        'PU5A' => 'PU 5A', 'PU5B' => 'PU 5B', 'PU6A' => 'PU 6A', 'PU6B' => 'PU 6B',
        'BUPUCONV' => 'Conversación (adultos)',
    ];

    /** nivel => siguiente nivel en otro programa */
    private const NEXT_ACROSS_PROGRAMS = [
        'PRIMARY6B' => 'HS1A',
    ];

    private bool $overwrite = false;

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $changes = [];

    /** @var list<string> */
    private array $missing = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->overwrite = (bool) $this->option('overwrite');
        $this->changes = [];
        $this->missing = [];

        DB::beginTransaction();
        try {
            $this->ensureAdultsProgram();
            $this->applyPrograms();
            $this->applyPrePrimaryLevels();
            foreach (self::LEVELS as $programCode => $levels) {
                $this->applyLevels($programCode, $levels);
            }
            $this->applyNextLevels();

            $this->changes === []
                ? $this->info('Sin cambios: la estructura ya está cargada.')
                : $this->table(['Programa / nivel', 'Campo', 'Valor nuevo'], $this->changes);
            foreach ($this->missing as $message) {
                $this->warn($message);
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info('[dry-run] No se guardó nada.');

                return self::SUCCESS;
            }

            if ($this->changes !== []) {
                AuditLog::create([
                    'action' => 'programs.cefr_structure_applied',
                    'payload' => ['changes' => count($this->changes), 'sample' => array_slice($this->changes, 0, 30), 'overwrite' => $this->overwrite, 'via' => 'programs:apply-cefr-structure'],
                ]);
            }
            DB::commit();
            $this->info('Estructura CEFR/GSE aplicada: '.count($this->changes).' cambios.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error('No se aplicó: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function ensureAdultsProgram(): void
    {
        $program = Program::query()->where('code', Program::ADULTS_CODE)->first();
        if (! $program) {
            $program = Program::create([
                'name' => 'Basic User – Prof. Use',
                'code' => Program::ADULTS_CODE,
                'status' => 'active',
                'description' => 'Programa de adultos: Basic User (BU), Independent User (IU) y Prof User (PU), 7 niveles.',
            ]);
            $this->changes[] = [Program::ADULTS_CODE, 'programa', 'creado'];
        }

        $total = count(self::ADULT_NAMES);
        $order = 0;
        foreach (self::ADULT_NAMES as $code => $name) {
            $order++;
            if (ProgramLevel::query()->where('code', $code)->exists()) {
                continue;
            }
            $level = $program->levels()->create([
                'name' => $name,
                'code' => $code,
                'sort_order' => $order,
                'program_total' => $total,
                'academic_hours' => 40,
                'reminder_days_before' => 5,
                'status' => 'active',
                'description' => "Nivel {$name} del programa de adultos.",
            ]);
            for ($lesson = 1; $lesson <= 20; $lesson++) {
                $level->lessons()->create([
                    'class_number' => $lesson,
                    'unit' => "Unidad {$lesson}",
                    'content' => "Contenido base de la clase {$lesson}",
                    'sort_order' => $lesson,
                ]);
            }
            $this->changes[] = [$code, 'nivel', 'creado'];
        }
    }

    private function applyPrograms(): void
    {
        foreach (self::PROGRAMS as $code => [$audience, $ageMin, $ageMax]) {
            $program = Program::query()->where('code', $code)->first();
            if (! $program) {
                $this->missing[] = "No existe el programa con código {$code}.";

                continue;
            }
            $this->fill($program, $code, ['audience' => $audience, 'age_min' => $ageMin, 'age_max' => $ageMax]);
            if (! $program->uses_cefr) {
                $program->uses_cefr = true;
                $this->changes[] = [$code, 'uses_cefr', 'sí'];
            }
            $program->save();
        }
    }

    /** Pre-Primary no tiene CEFR en la guía: solo nivel y parte (1A … 6B). */
    private function applyPrePrimaryLevels(): void
    {
        $program = Program::query()->where('code', 'PREP')->first();
        foreach ($program?->levels ?? [] as $level) {
            if (preg_match('/(\d+)\s*([AB])$/i', $level->name, $match)) {
                $this->fill($level, $level->code, ['level_number' => (int) $match[1], 'part' => strtoupper($match[2])]);
                $level->save();
            }
        }
    }

    /** @param  array<string, array{0: ?int, 1: ?string, 2: string, 3: ?string}>  $levels */
    private function applyLevels(string $programCode, array $levels): void
    {
        $program = Program::query()->where('code', $programCode)->first();
        if (! $program) {
            return;
        }

        foreach ($levels as $code => [$number, $part, $cefr, $book]) {
            $level = ProgramLevel::query()->where('program_id', $program->id)->where('code', $code)->first();
            if (! $level) {
                $this->missing[] = "No existe el nivel {$code} en {$program->name}.";

                continue;
            }
            [$gseMin, $gseMax] = CefrScale::gseRange($cefr) ?? [null, null];
            $this->fill($level, $code, [
                'level_number' => $number,
                'part' => $part,
                'cefr_label' => $cefr,
                'gse_min' => $gseMin,
                'gse_max' => $gseMax,
                'book' => $book,
            ]);
            $level->save();
        }
    }

    private function applyNextLevels(): void
    {
        foreach (self::NEXT_ACROSS_PROGRAMS as $fromCode => $toCode) {
            $from = ProgramLevel::query()->where('code', $fromCode)->first();
            $to = ProgramLevel::query()->where('code', $toCode)->first();
            if ($from && $to) {
                $this->fill($from, $fromCode, ['next_program_level_id' => $to->id], $toCode);
                $from->save();
            }
        }
    }

    /** @param  array<string, mixed>  $values */
    private function fill(Program|ProgramLevel $model, string $label, array $values, ?string $display = null): void
    {
        foreach ($values as $field => $value) {
            if ($value === null || $model->{$field} == $value) {
                continue;
            }
            if (! $this->overwrite && $model->{$field} !== null && $model->{$field} !== '') {
                continue;
            }
            $model->{$field} = $value;
            $this->changes[] = [$label, $field, (string) ($display ?? $value)];
        }
    }
}
