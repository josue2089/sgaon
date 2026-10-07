<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Support\AlertEngine;
use App\Support\FinanceReconcile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Mensualidades automáticas que nacieron vencidas porque el curso se cargó en el sistema cuando ya
 * estaba en marcha (julio 2026): el nivel se pagó por fuera. Ticket DevTeam #214.
 */
class VoidPreSystemTuition extends Command
{
    public const VOID_REASON = 'Nivel cursado antes del sistema';

    protected $signature = 'finance:void-pre-system-tuition
        {--dry-run : Solo lista las mensualidades, sin anularlas}
        {--days=7 : Días mínimos entre el vencimiento y la creación del cargo}
        {--ids= : Lista de IDs de cargo (separados por coma) para anular solo esos}';

    protected $description = 'Anula mensualidades automáticas sin pagos que nacieron vencidas (niveles cursados antes del sistema).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = max(0, (int) $this->option('days'));
        $ids = collect(explode(',', (string) $this->option('ids')))->map(fn ($id) => (int) trim($id))->filter();

        $charges = Charge::query()
            ->with(['student', 'campus'])
            ->where('charge_type', 'tuition')
            ->where('origin', 'enrollment_auto')
            ->whereNull('voided_at')
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('campus_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Charge $charge) => $charge->due_date
                && $charge->due_date->copy()->addDays($days)->lt(Carbon::parse($charge->created_at)->startOfDay())
                && FinanceReconcile::paidTotalForCharge($charge) <= 0)
            ->values();

        $this->info(($dryRun ? '[dry-run] ' : '').'Mensualidades a anular: '.$charges->count());
        if ($charges->isNotEmpty()) {
            $this->table(['Cargo', 'Sede', 'Alumno', 'Concepto', 'Monto', 'Vence', 'Creado'], $charges->map(fn (Charge $charge) => [
                $charge->id,
                $charge->campus?->name,
                $charge->student?->full_name,
                mb_strimwidth((string) $charge->concept, 0, 45, '…'),
                $charge->amount.' '.$charge->currencyCode(),
                $charge->due_date?->toDateString(),
                Carbon::parse($charge->created_at)->toDateString(),
            ])->all());
        }

        if ($dryRun || $charges->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($charges as $charge) {
            $charge->update([
                'voided_at' => now(),
                'void_reason' => self::VOID_REASON,
                'status' => 'void',
            ]);
        }

        $charges->pluck('student_id')->unique()->each(fn ($studentId) => AlertEngine::evaluateFinanceForStudent((int) $studentId));

        $this->info('Mensualidades anuladas: '.$charges->count().'.');

        return self::SUCCESS;
    }
}
