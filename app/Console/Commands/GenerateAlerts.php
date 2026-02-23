<?php

namespace App\Console\Commands;

use App\Models\ClassSession;
use App\Models\Student;
use App\Support\AlertEngine;
use Illuminate\Console\Command;

class GenerateAlerts extends Command
{
    protected $signature = 'generate:alerts';

    protected $description = 'Genera/recalcula alertas académicas y financieras.';

    public function handle(): int
    {
        $sessions = ClassSession::pluck('id');
        foreach ($sessions as $sessionId) {
            AlertEngine::evaluateAttendanceForSession((int) $sessionId);
        }

        $students = Student::pluck('id');
        foreach ($students as $studentId) {
            AlertEngine::evaluateFinanceForStudent((int) $studentId);
        }

        $this->info('Alertas recalculadas. Sesiones: '.$sessions->count().' | Alumnos: '.$students->count());

        return self::SUCCESS;
    }
}
