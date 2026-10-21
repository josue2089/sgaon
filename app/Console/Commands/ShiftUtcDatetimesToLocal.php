<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * #368: la app corría en UTC y pasó a America/Caracas. Las columnas TIMESTAMP se corrigen solas
 * (MySQL las guarda en UTC y la conexión ahora las lee en -04:00), pero las DATETIME guardaron la
 * hora UTC como texto y quedarían 4 horas adelantadas. Este comando las lleva a hora de Caracas.
 *
 * Cada valor se compara con un TIMESTAMP de la misma fila escrito en el mismo momento: si ya
 * coincide (menos de 1 hora de diferencia) se considera hora local y no se toca.
 */
class ShiftUtcDatetimesToLocal extends Command
{
    protected $signature = 'data:shift-utc-datetimes {--dry-run : Muestra qué cambiaría sin guardar}';

    protected $description = 'Pasa a hora de Caracas las columnas DATETIME guardadas en UTC antes del #368';

    private const AUDIT_ACTION = 'data.utc_datetimes_shifted';

    private const OFFSET_HOURS = 4;

    /** tabla => [columna DATETIME => TIMESTAMP de referencia escrito en el mismo momento] */
    private const COLUMNS = [
        'payments' => ['paid_at_datetime' => 'created_at', 'voided_at' => 'updated_at'],
        'charges' => ['voided_at' => 'updated_at'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (AuditLog::query()->where('action', self::AUDIT_ACTION)->exists()) {
            $this->warn('Ya se aplicó antes (ver auditoría '.self::AUDIT_ACTION.'). No se hace nada.');

            return self::SUCCESS;
        }

        $summary = [];
        $samples = [];

        DB::beginTransaction();
        try {
            foreach (self::COLUMNS as $table => $columns) {
                foreach ($columns as $column => $reference) {
                    $shifted = 0;
                    $skipped = 0;
                    $rows = DB::table($table)->whereNotNull($column)->get(['id', $column, $reference]);
                    foreach ($rows as $row) {
                        $value = Carbon::parse($row->{$column});
                        if ($row->{$reference} !== null && abs($value->diffInMinutes(Carbon::parse($row->{$reference}))) < 60) {
                            $skipped++;

                            continue;
                        }
                        $new = $value->copy()->subHours(self::OFFSET_HOURS);
                        DB::table($table)->where('id', $row->id)->update([$column => $new->format('Y-m-d H:i:s')]);
                        $shifted++;
                        if (count($samples) < 8) {
                            $samples[] = ["{$table}.{$column}", $row->id, $value->format('Y-m-d H:i'), $new->format('Y-m-d H:i')];
                        }
                    }
                    $summary[] = ["{$table}.{$column}", $shifted, $skipped];
                }
            }

            $this->table(['Columna', 'Se corrigen (-4 h)', 'Ya en hora local'], $summary);
            if ($samples !== []) {
                $this->table(['Columna', 'ID', 'Antes', 'Después'], $samples);
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info('[dry-run] No se guardó nada.');

                return self::SUCCESS;
            }

            AuditLog::create([
                'action' => self::AUDIT_ACTION,
                'payload' => ['columns' => $summary, 'via' => 'data:shift-utc-datetimes'],
            ]);
            DB::commit();
            $this->info('Fechas corregidas a hora de Caracas.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->error('No se aplicó: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
