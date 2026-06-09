<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Services\HistoricalStudentImportService;
use Illuminate\Console\Command;

class ImportStudentsHistoricalLedger extends Command
{
    protected $signature = 'import:students-historical-ledger {file} {--campus=PICACHO} {--dry-run}';

    protected $description = 'Importa alumnos históricos desde Excel ledger 13-16 Picacho.';

    public function handle(HistoricalStudentImportService $importService): int
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
            $format = $importService->detectFormat($file);
            if ($format !== 'ledger') {
                $this->error('El archivo no corresponde al formato ledger 13-16.');

                return self::FAILURE;
            }
            $preview = $importService->buildPreview($file, $campus->id, basename($file));
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
