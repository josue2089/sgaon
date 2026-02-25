<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\Enrollment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringCharges extends Command
{
    protected $signature = 'finance:generate-recurring-charges {--month=} {--amount=}';

    protected $description = 'Genera cargos mensuales para inscripciones activas.';

    public function handle(): int
    {
        $monthInput = (string) $this->option('month');
        $targetMonth = $monthInput !== '' ? Carbon::parse($monthInput.'-01') : now()->startOfMonth();
        $monthKey = $targetMonth->format('Y-m');
        $dueDate = $targetMonth->copy()->day(5)->toDateString();
        $amount = (float) ($this->option('amount') ?: config('finance.default_monthly_charge_amount', 0));
        $baseConcept = (string) config('finance.default_monthly_charge_concept', 'Mensualidad académica');

        if ($amount <= 0) {
            $this->error('Monto inválido. Define --amount o config finance.default_monthly_charge_amount > 0.');
            return self::FAILURE;
        }

        $enrollments = Enrollment::query()
            ->with(['student', 'group.course'])
            ->where('status', 'active')
            ->whereHas('group', fn ($query) => $query->where('status', 'active'))
            ->get();

        $created = 0;
        foreach ($enrollments as $enrollment) {
            if (! $enrollment->student_id || ! $enrollment->campus_id) {
                continue;
            }

            $concept = trim($baseConcept.' '.$monthKey.' · '.($enrollment->group->name ?? 'Grupo'));

            $charge = Charge::firstOrCreate(
                [
                    'student_id' => $enrollment->student_id,
                    'concept' => $concept,
                    'due_date' => $dueDate,
                ],
                [
                    'campus_id' => $enrollment->campus_id,
                    'amount' => $amount,
                    'status' => 'pending',
                    'notes' => 'Auto-generado por inscripción activa #'.$enrollment->id,
                ]
            );

            if ($charge->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("Cargos recurrentes procesados para {$monthKey}. Nuevos: {$created}. Inscripciones activas: ".$enrollments->count().'.');

        return self::SUCCESS;
    }
}
