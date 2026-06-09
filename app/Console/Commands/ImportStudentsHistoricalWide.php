<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Services\HistoricalStudentImportService;
use Illuminate\Console\Command;

class ImportStudentsHistoricalWide extends Command
{
    protected $signature = 'import:students-historical-wide {file} {--campus=} {--dry-run}';

    protected $description = 'Importa alumnos históricos desde Excel formato ancho (Picacho/Cascada).';

    public function handle(HistoricalStudentImportService $importService): int
    {
        $file = (string) $this->argument('file');

        if (! file_exists($file)) {
            $this->error('Archivo no encontrado: '.$file);

            return self::FAILURE;
        }

        $campusId = null;
        $campusCode = (string) $this->option('campus');
        if ($campusCode !== '') {
            $campus = Campus::query()->where('code', strtoupper($campusCode))->first();
            if (! $campus) {
                $this->error('Campus no encontrado con código: '.$campusCode);

                return self::FAILURE;
            }
            $campusId = $campus->id;
        }

        try {
            $format = $importService->detectFormat($file);
            if ($format !== 'wide') {
                $this->error('El archivo no corresponde al formato ancho.');

                return self::FAILURE;
            }
            $preview = $importService->buildPreview($file, $campusId, basename($file));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->reportPreview($preview);

        if ($preview->validCount() === 0) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $importService->import($preview, $dryRun);
        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Creados: {$result->created}. Actualizados: {$result->updated}. Fallidos: {$result->failed}.");

        return self::SUCCESS;
    }

    private function reportPreview(\App\Services\Import\HistoricalImportPreviewResult $preview): void
    {
        $this->info("Archivo: {$preview->filename} ({$preview->format})");
        $this->info('Filas: '.count($preview->rows)." | Válidas: {$preview->validCount()} | Errores: {$preview->errorCount()}");

        foreach ($preview->rows as $row) {
            if ($row->isValid && $row->warnings === []) {
                continue;
            }
            if (! $row->isValid) {
                $this->warn("Fila {$row->lineNumber}: ".implode(' ', $row->errors));
            } elseif ($row->warnings !== []) {
                $this->line("Fila {$row->lineNumber}: ".implode(' ', $row->warnings));
            }
        }
    }
}
