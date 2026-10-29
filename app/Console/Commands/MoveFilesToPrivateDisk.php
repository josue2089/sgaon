<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\PrivateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * #438: mueve comprobantes, reposos, adjuntos de alumnos y exportaciones del disco público al privado.
 * La ruta relativa no cambia, así que la base de datos no se toca: solo se copia el archivo y se borra
 * la copia pública (la URL /storage/... deja de funcionar). Se puede correr varias veces.
 */
class MoveFilesToPrivateDisk extends Command
{
    protected $signature = 'files:move-private {--dry-run : Muestra qué se movería sin tocar archivos}';

    protected $description = 'Mueve comprobantes, reposos, adjuntos y exportaciones al disco privado';

    /** tabla => columna con la ruta relativa */
    private const SOURCES = [
        'charge_payment_requests' => 'proof_path',
        'makeup_request_attachments' => 'file_path',
        'makeup_requests' => 'medical_support_path',
        'student_attachments' => 'file_path',
        'report_exports' => 'file_path',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk(PrivateFiles::LEGACY_DISK);
        $private = Storage::disk(PrivateFiles::DISK);
        $summary = [];
        $totals = ['moved' => 0, 'already' => 0, 'missing' => 0];

        foreach (self::SOURCES as $table => $column) {
            $counts = ['moved' => 0, 'already' => 0, 'missing' => 0];
            $paths = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->distinct()->pluck($column);

            foreach ($paths as $path) {
                if (! $public->exists($path)) {
                    $private->exists($path) ? $counts['already']++ : $counts['missing']++;

                    continue;
                }
                if (! $dryRun) {
                    if (! $private->exists($path)) {
                        $private->writeStream($path, $public->readStream($path));
                    }
                    if ($private->size($path) !== $public->size($path)) {
                        $this->error("No se pudo copiar {$path}; se deja en el disco público.");

                        continue;
                    }
                    $public->delete($path);
                }
                $counts['moved']++;
            }

            $summary[] = ["{$table}.{$column}", $counts['moved'], $counts['already'], $counts['missing']];
            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }
        }

        $this->table(['Origen', $dryRun ? 'Se moverían' : 'Movidos', 'Ya privados', 'Sin archivo'], $summary);

        if ($dryRun) {
            $this->info('[dry-run] No se movió nada.');

            return self::SUCCESS;
        }

        AuditLog::create([
            'action' => 'data.files_moved_private',
            'payload' => ['totals' => $totals, 'via' => 'files:move-private'],
        ]);
        $this->info("Archivos movidos al disco privado: {$totals['moved']}.");

        return self::SUCCESS;
    }
}
