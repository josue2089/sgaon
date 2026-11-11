<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Solvencia por sede y tipo de programa (regular / extracurricular): cuántos alumnos están al día y cuántos deben. #514
 *
 * - Alumnos: los que tienen inscripción activa en esa sede y tipo, más los que deben aunque ya no estén inscritos.
 * - Deuda: cargos no anulados con saldo pendiente (monto menos pagos no anulados, directos o por asignación).
 *   Corte "vencida": vencimiento hasta hoy. Corte por mes: vencimiento dentro de ese mes.
 * - El tipo del cargo sale del programa de su curso (o del curso de su inscripción); sin curso, cuenta como regular.
 */
final class SolvencyReport
{
    public const CATEGORIES = ['regular' => 'Regular', 'extracurricular' => 'Extracurricular'];

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, mixed>, students: Collection<int, array<string, mixed>>, cutLabel: string}
     */
    public static function build(?User $user, ?int $campusId = null, ?string $month = null): array
    {
        $today = now()->startOfDay();
        $monthStart = $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : null;

        $campuses = CampusScope::apply(Campus::query()->orderBy('name'), $user, 'id')
            ->when($campusId, fn ($query) => $query->whereKey($campusId))
            ->get(['id', 'name'])
            ->keyBy('id');
        $campusIds = $campuses->keys()->all();

        /** @var array<string, array<string, mixed>> $students  clave "sede|tipo|alumno" */
        $students = [];
        $entry = function (int $campus, string $category, $student) use (&$students): array {
            $key = $campus.'|'.$category.'|'.$student->id;
            $students[$key] ??= [
                'campus_id' => $campus,
                'category' => $category,
                'student' => $student,
                'courses' => [],
                'active' => false,
                'charges' => [],
                'debt' => [],
                'max_days' => 0,
            ];

            return [$key];
        };

        $enrollments = Enrollment::query()
            ->with(['student.representatives', 'group.course.program'])
            ->where('status', 'active')
            ->whereIn('campus_id', $campusIds ?: [0])
            ->whereHas('student')
            ->get();
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->group?->course;
            [$key] = $entry((int) $enrollment->campus_id, self::categoryOf($course?->program?->is_extracurricular), $enrollment->student);
            $students[$key]['active'] = true;
            if ($course) {
                $students[$key]['courses'][$course->id] = $course->name;
            }
        }

        $charges = Charge::query()
            ->with(['student.representatives', 'course.program', 'enrollment.group.course.program'])
            ->whereNull('voided_at')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereIn('campus_id', $campusIds ?: [0])
            ->whereHas('student')
            ->when($monthStart,
                fn ($query) => $query->whereDate('due_date', '>=', $monthStart->toDateString())->whereDate('due_date', '<=', $monthStart->copy()->endOfMonth()->toDateString()),
                fn ($query) => $query->whereNotNull('due_date')->whereDate('due_date', '<=', $today->toDateString()))
            ->get();
        $paid = self::paidByCharge($charges->pluck('id')->all());

        foreach ($charges as $charge) {
            $outstanding = max(0, FinanceReconcile::cents($charge->amount) - ($paid[$charge->id] ?? 0));
            if ($outstanding <= 0) {
                continue;
            }
            $course = $charge->course ?? $charge->enrollment?->group?->course;
            [$key] = $entry((int) $charge->campus_id, self::categoryOf($course?->program?->is_extracurricular), $charge->student);
            $currency = $charge->currencyCode();
            $students[$key]['debt'][$currency] = ($students[$key]['debt'][$currency] ?? 0) + $outstanding;
            $students[$key]['charges'][] = $charge->concept;
            if ($course && ! isset($students[$key]['courses'][$course->id])) {
                $students[$key]['courses'][$course->id] = $course->name;
            }
            $days = $charge->due_date && $charge->due_date->lt($today) ? (int) $charge->due_date->diffInDays($today) : 0;
            $students[$key]['max_days'] = max($students[$key]['max_days'], $days);
        }

        $list = collect($students)->map(function (array $row) use ($campuses) {
            $representative = $row['student']->representatives->first();
            ksort($row['debt']);

            return $row + [
                'campus_name' => $campuses->get($row['campus_id'])?->name ?? 'N/D',
                'owes' => $row['debt'] !== [],
                'debt_amounts' => array_map(fn (int $cents) => round($cents / 100, 2), $row['debt']),
                'representative' => $representative,
                'phone' => $representative?->mobile_phone ?: ($representative?->phone ?: ($row['student']->mobile_phone ?? $row['student']->phone ?? null)),
            ];
        })->sortBy(fn (array $row) => [$row['campus_name'], $row['category'], $row['student']->full_name])->values();

        $rows = collect();
        foreach ($campuses as $campus) {
            foreach (array_keys(self::CATEGORIES) as $category) {
                $subset = $list->where('campus_id', $campus->id)->where('category', $category);
                if ($subset->isEmpty()) {
                    continue;
                }
                $rows->push(['campus_id' => $campus->id, 'campus_name' => $campus->name, 'category' => $category] + self::summarize($subset));
            }
        }

        return [
            'rows' => $rows,
            'totals' => self::summarize($list),
            'students' => $list,
            'cutLabel' => $monthStart
                ? 'Cargos que vencen en '.$monthStart->locale('es')->isoFormat('MMMM YYYY')
                : 'Deuda vencida al '.$today->format('d/m/Y'),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $subset */
    private static function summarize(Collection $subset): array
    {
        $owing = $subset->where('owes', true);
        $amounts = [];
        foreach ($owing as $row) {
            foreach ($row['debt'] as $currency => $cents) {
                $amounts[$currency] = ($amounts[$currency] ?? 0) + $cents;
            }
        }
        ksort($amounts);

        return [
            'active' => $subset->where('active', true)->count(),
            'up_to_date' => $subset->where('active', true)->where('owes', false)->count(),
            'owing' => $owing->count(),
            'owing_inactive' => $owing->where('active', false)->count(),
            'over_30' => $owing->where('max_days', '>', 30)->count(),
            'amounts' => array_map(fn (int $cents) => round($cents / 100, 2), $amounts),
        ];
    }

    public static function categoryOf(?bool $isExtracurricular): string
    {
        return $isExtracurricular ? 'extracurricular' : 'regular';
    }

    /**
     * Pagado por cargo en centavos, en dos consultas: pagos directos sin asignaciones y asignaciones de pagos no anulados.
     * Misma regla que FinanceReconcile::paidTotalForCharge.
     *
     * @param  list<int>  $chargeIds
     * @return array<int, int>
     */
    private static function paidByCharge(array $chargeIds): array
    {
        $paid = [];
        foreach (array_chunk($chargeIds, 500) as $chunk) {
            $direct = Payment::query()
                ->whereIn('charge_id', $chunk)
                ->whereNull('voided_at')
                ->whereDoesntHave('allocations')
                ->selectRaw('charge_id, SUM(amount) as total')
                ->groupBy('charge_id')
                ->pluck('total', 'charge_id');
            $allocated = PaymentAllocation::query()
                ->whereIn('charge_id', $chunk)
                ->whereHas('payment', fn ($query) => $query->whereNull('voided_at'))
                ->selectRaw('charge_id, SUM(amount_applied) as total')
                ->groupBy('charge_id')
                ->pluck('total', 'charge_id');
            foreach ($chunk as $id) {
                $paid[$id] = FinanceReconcile::cents((float) ($direct[$id] ?? 0) + (float) ($allocated[$id] ?? 0));
            }
        }

        return $paid;
    }
}
