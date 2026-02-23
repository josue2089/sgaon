<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Console\Command;

class DataReconcile extends Command
{
    protected $signature = 'data:reconcile {--year=}';

    protected $description = 'Resumen de conciliación de datos académicos y financieros.';

    public function handle(): int
    {
        $year = $this->normalizeYear($this->option('year'));

        $studentsQuery = Student::query();
        $activeStudentsQuery = Student::where('status', 'active');
        $enrollmentsQuery = Enrollment::query();
        $chargesQuery = Charge::query();
        $paymentsQuery = Payment::query();

        if ($year !== null) {
            $studentsQuery->whereYear('created_at', $year);
            $activeStudentsQuery->whereYear('created_at', $year);
            $enrollmentsQuery->whereYear('enrolled_at', $year);
            $chargesQuery->whereYear('due_date', $year);
            $paymentsQuery->whereYear('paid_at', $year);
        }

        $students = $studentsQuery->count();
        $activeStudents = $activeStudentsQuery->count();
        $enrollments = $enrollmentsQuery->count();
        $charges = (float) $chargesQuery->sum('amount');
        $payments = (float) $paymentsQuery->sum('amount');
        $balance = $charges - $payments;

        $title = $year ? "Conciliación {$year}" : 'Conciliación Global';
        $this->info($title);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Alumnos totales', (string) $students],
                ['Alumnos activos', (string) $activeStudents],
                ['Inscripciones', (string) $enrollments],
                ['Total cargos', number_format($charges, 2)],
                ['Total pagos', number_format($payments, 2)],
                ['Saldo', number_format($balance, 2)],
            ],
        );

        $this->table(
            ['Estado cargo', 'Cantidad'],
            $this->chargeStatuses($year),
        );

        return self::SUCCESS;
    }

    private function normalizeYear(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $year = (int) $raw;

        if ($year < 2000 || $year > 2100) {
            return null;
        }

        return $year;
    }

    private function chargeStatuses(?int $year): array
    {
        $query = Charge::query()->selectRaw('status, COUNT(*) as total')->groupBy('status');
        if ($year !== null) {
            $query->whereYear('due_date', $year);
        }

        $rows = $query->pluck('total', 'status');
        $result = [];

        foreach (['pending', 'partial', 'paid', 'overdue'] as $status) {
            $result[] = [$status, (string) ($rows[$status] ?? 0)];
        }

        return $result;
    }
}
