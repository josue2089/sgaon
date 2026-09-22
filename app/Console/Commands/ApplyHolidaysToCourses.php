<?php

namespace App\Console\Commands;

use App\Services\HolidayCalendarSync;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ApplyHolidaysToCourses extends Command
{
    protected $signature = 'courses:apply-holidays
        {--campus= : ID de la sede (vacío = todas)}
        {--from= : Recalcular cursos que terminan desde esta fecha (Y-m-d). Por defecto, inicio del año}';

    protected $description = 'Recalcula el calendario de los cursos activos para respetar los feriados vigentes.';

    public function handle(HolidayCalendarSync $sync): int
    {
        $campusId = $this->option('campus') !== null ? (int) $this->option('campus') : null;
        $from = $this->option('from') ? Carbon::parse((string) $this->option('from'))->startOfDay() : now()->startOfYear();

        $result = $sync->resyncForHoliday($campusId, $from);

        $this->info("Cursos recalculados: {$result['updated']}");

        foreach ($result['skipped'] as $course => $reason) {
            $this->warn("Omitido · {$course}: {$reason}");
        }

        foreach ($result['conflicts'] as $course => $dates) {
            $this->warn("Con asistencia en feriado · {$course}: ".implode(', ', $dates));
        }

        return self::SUCCESS;
    }
}
