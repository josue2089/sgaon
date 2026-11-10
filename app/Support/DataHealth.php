<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\RepresentativeMerger;

/** Verificaciones de solo lectura para la pantalla "Salud de datos". #445 */
final class DataHealth
{
    /** @return array<string, int|float> */
    public static function summary(): array
    {
        return [
            'students' => Student::query()->count(),
            'active_students' => Student::query()->where('status', 'active')->count(),
            'enrollments' => Enrollment::query()->count(),
            'charges_total' => (float) Charge::query()->whereNull('voided_at')->sum('amount'),
            'payments_total' => (float) Payment::query()->whereNull('voided_at')->sum('amount'),
        ];
    }

    /** @return array<string, int> */
    public static function chargeStatuses(): array
    {
        return Charge::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all();
    }

    /** Cargos vigentes cuyo estado guardado no coincide con sus pagos y su vencimiento. */
    public static function staleCharges(int $limit = 2000): int
    {
        $stale = 0;
        Charge::query()->with('payments')->whereNull('voided_at')->latest('id')->limit($limit)->get()
            ->each(function (Charge $charge) use (&$stale) {
                if ($charge->status !== FinanceReconcile::expectedStatus($charge)) {
                    $stale++;
                }
            });

        return $stale;
    }

    /** @return list<array{course: ?Course, groups: int, group_ids: string}> */
    public static function duplicateGroups(): array
    {
        $duplicates = Group::query()
            ->selectRaw('course_id, COUNT(*) as total')
            ->whereNotNull('course_id')
            ->groupBy('course_id')
            ->having('total', '>', 1)
            ->pluck('total', 'course_id');
        $courses = Course::query()->with('campus')->whereIn('id', $duplicates->keys())->get()->keyBy('id');

        return $duplicates->map(fn ($total, $courseId) => [
            'course' => $courses->get($courseId),
            'groups' => (int) $total,
            'group_ids' => Group::query()->where('course_id', $courseId)->orderBy('id')->pluck('id')->implode(', '),
        ])->values()->all();
    }

    public static function teachersWithoutAccess(): int
    {
        return Teacher::query()->where('status', '!=', 'inactive')->whereNull('user_id')->count();
    }

    /** @return list<array{ids: list<int>, reason: string}> */
    public static function representativesToReview(): array
    {
        return app(RepresentativeMerger::class)->plan()['review'];
    }

    public static function representativesToMerge(): int
    {
        return count(app(RepresentativeMerger::class)->plan()['merge']);
    }
}
