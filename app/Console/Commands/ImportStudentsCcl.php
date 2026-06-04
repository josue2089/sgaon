<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Services\StudentBulkImportService;
use Illuminate\Console\Command;

class ImportStudentsCcl extends Command
{
    protected $signature = 'import:students-ccl {file} {--campus=PICACHO} {--dry-run}';

    protected $description = 'Importa alumnos desde Excel CCL (ficha y programa de inscripción).';

    public function handle(StudentBulkImportService $importService): int
    {
        $file = (string) $this->argument('file');

        if (! file_exists($file)) {
            $this->error('Archivo no encontrado: '.$file);

            return self::FAILURE;
        }

        $campus = Campus::query()
            ->where('code', strtoupper((string) $this->option('campus')))
            ->first();

        if (! $campus) {
            $this->error('Campus no encontrado con código: '.$this->option('campus'));

            return self::FAILURE;
        }

        try {
            $preview = $importService->buildPreview($file, $campus->id, basename($file));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Archivo: {$preview->filename}");
        $this->info("Filas: ".count($preview->rows)." | Válidas: {$preview->validCount()} | Errores: {$preview->errorCount()}");

        if ($preview->errorCount() > 0) {
            foreach ($preview->rows as $row) {
                if ($row->isValid) {
                    continue;
                }
                $this->warn("Fila {$row->lineNumber}: ".implode(' ', $row->errors));
            }
        }

        if ($preview->validCount() === 0) {
            $this->error('No hay filas válidas para importar.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $importService->import($preview, $dryRun);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Creados: {$result->created}. Actualizados: {$result->updated}. Fallidos: {$result->failed}.");

        return self::SUCCESS;
    }
}
