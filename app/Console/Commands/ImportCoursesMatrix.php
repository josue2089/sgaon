<?php

namespace App\Console\Commands;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Course;
use Illuminate\Console\Command;

class ImportCoursesMatrix extends Command
{
    protected $signature = 'import:courses-matrix {--file=} {--campus=PICACHO}';

    protected $description = 'Importa cursos/niveles desde un archivo de texto o CSV.';

    public function handle(): int
    {
        $file = (string) $this->option('file');

        if ($file === '' || ! file_exists($file)) {
            $this->error('Debe indicar --file con una ruta válida.');

            return self::FAILURE;
        }

        $campus = Campus::firstOrCreate(
            ['code' => strtoupper((string) $this->option('campus'))],
            ['name' => 'Sede Picacho', 'city' => 'San Antonio de los Altos', 'country' => 'Venezuela', 'status' => 'active'],
        );

        $content = file_get_contents($file) ?: '';
        preg_match_all('/(Pre-?Primary\s*\d+\s*[AB]?|Primary\s*\d+\s*[AB]?|High\s*School\s*\d+\s*[AB]?|HS\s*\d+\s*[AB]?)/i', $content, $matches);

        $detected = array_values(array_unique(array_map(static function ($name) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $name)));
        }, $matches[0] ?? [])));

        if ($detected === []) {
            $this->warn('No se detectaron cursos automáticamente.');

            return self::SUCCESS;
        }

        foreach ($detected as $name) {
            if (str_contains($name, 'PRE')) {
                $levelName = 'Pre-Primary';
            } elseif (str_contains($name, 'PRIMARY') && ! str_contains($name, 'PRE')) {
                $levelName = 'Primary';
            } else {
                $levelName = 'High School';
            }

            $level = AcademicLevel::firstOrCreate(
                ['campus_id' => $campus->id, 'name' => $levelName],
                ['code' => strtoupper(str_replace(' ', '_', $levelName)), 'sort_order' => 0],
            );

            Course::firstOrCreate(
                ['campus_id' => $campus->id, 'name' => $name],
                ['academic_level_id' => $level->id, 'code' => str_replace(' ', '_', $name), 'status' => 'active'],
            );
        }

        $this->info('Importación de cursos completada. Registros detectados: '.count($detected));

        return self::SUCCESS;
    }
}
