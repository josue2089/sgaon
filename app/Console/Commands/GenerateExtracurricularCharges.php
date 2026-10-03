<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Services\EnrollmentBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateExtracurricularCharges extends Command
{
    protected $signature = 'finance:generate-extracurricular-charges
        {--month= : Mes a generar (YYYY-MM). Por defecto, el mes en curso}
        {--dry-run : Solo cuenta las cuotas que se crearían}';

    protected $description = 'Crea la cuota mensual de cada inscripción activa en cursos extracurriculares (idempotente).';

    public function handle(EnrollmentBillingService $billing): int
    {
        $monthOption = (string) $this->option('month');
        $month = $monthOption !== '' ? Carbon::parse($monthOption.'-01') : now();
        $month = $month->copy()->startOfMonth();
        $dryRun = (bool) $this->option('dry-run');

        $enrollments = Enrollment::query()
            ->with(['group.course.program', 'group.course.programLevel.program', 'student.representatives'])
            ->where('status', 'active')
            ->whereHas('group.course', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('program', fn ($program) => $program->where('is_extracurricular', true)))
            ->get();

        $created = 0;
        foreach ($enrollments as $enrollment) {
            if ($dryRun) {
                $created += (int) $this->wouldCreate($enrollment, $month);

                continue;
            }

            if ($billing->createMonthlyCharge($enrollment, $month)) {
                $created++;
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '').'Cuotas extracurriculares '.$month->format('Y-m').': '.$created.' nuevas de '.$enrollments->count().' inscripciones activas.');

        return self::SUCCESS;
    }

    private function wouldCreate(Enrollment $enrollment, Carbon $month): bool
    {
        $course = $enrollment->group->course;
        $first = collect([$course->start_date, $enrollment->enrolled_at ? Carbon::parse($enrollment->enrolled_at) : null])->filter()->max();

        return $first
            && $month->gte($first->copy()->startOfMonth())
            && (! $course->end_date || $month->lte($course->end_date->copy()->startOfMonth()))
            && ! $enrollment->charges()
                ->where('charge_type', 'tuition')
                ->where('billing_period_label', $month->format('Y-m'))
                ->whereNull('voided_at')
                ->exists();
    }
}
