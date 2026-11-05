<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Group;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\FinanceReconcile;
use App\Support\RenewalEnrollmentEligibility;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Retirar a un alumno de un curso o cambiarlo a otro, con sus cargos, en una sola transacción. #441 */
class EnrollmentChangeService
{
    /** Los cargos pasan al curso nuevo y no se genera mensualidad nueva. */
    public const CHARGES_MOVE = 'move';

    /** Se anulan los cargos sin pagar y se genera la mensualidad del curso nuevo. */
    public const CHARGES_VOID = 'void';

    public function __construct(
        private readonly ChargeVoidService $charges,
        private readonly EnrollmentBillingService $billing,
    ) {
    }

    /** @return array{voided: int, pending: int} cargos anulados y cargos sin pagar que siguen vigentes */
    public function withdraw(Enrollment $enrollment, CarbonInterface $date, string $reason, bool $voidFutureCharges, Request $request): array
    {
        return DB::transaction(function () use ($enrollment, $date, $reason, $voidFutureCharges, $request) {
            $enrollment = $this->lockActive($enrollment);

            $voided = $voidFutureCharges
                ? $this->voidUnpaid(
                    Charge::query()->where('enrollment_id', $enrollment->id)->whereDate('due_date', '>', $date->toDateString()),
                    'Retiro del curso: '.$reason,
                    $request,
                )
                : 0;

            $this->markWithdrawn($enrollment, $date, $reason);
            AuditTrail::log($request, 'enrollment.withdraw', $enrollment, [
                'date' => $date->toDateString(),
                'reason' => $reason,
                'voided_charges' => $voided,
            ]);

            return ['voided' => $voided, 'pending' => $this->pendingCount($enrollment)];
        });
    }

    public function transfer(Enrollment $enrollment, Group $target, CarbonInterface $date, string $reason, string $chargesMode, Request $request): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $target, $date, $reason, $chargesMode, $request) {
            $enrollment = $this->lockActive($enrollment);
            $target = Group::query()->with('course')->lockForUpdate()->findOrFail($target->id);
            $this->assertCanJoin($enrollment, $target);

            $new = Enrollment::updateOrCreate(
                ['student_id' => $enrollment->student_id, 'group_id' => $target->id],
                ['campus_id' => $target->campus_id, 'enrolled_at' => $date->toDateString(), 'status' => 'active', 'progress' => 0],
            );

            // Las recuperativas siguen ligadas a la inasistencia del curso anterior.
            $tuitionAndOthers = Charge::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('charge_type', '!=', 'makeup');
            $voided = 0;
            if ($chargesMode === self::CHARGES_MOVE) {
                (clone $tuitionAndOthers)->whereNull('voided_at')->update([
                    'enrollment_id' => $new->id,
                    'course_id' => $target->course_id,
                    'group_id' => $target->id,
                    'period_id' => $target->course?->period_id,
                ]);
            } else {
                $voided = $this->voidUnpaid($tuitionAndOthers, 'Cambio de curso: '.$reason, $request);
            }

            $courseName = $target->course?->name ?? $target->name;
            $this->markWithdrawn($enrollment, $date, 'Cambió a '.$courseName.'. '.$reason);

            if ($chargesMode === self::CHARGES_VOID) {
                $this->billing->createTuitionCharge($new, $request);
            }

            AuditTrail::log($request, 'enrollment.transfer', $new, [
                'from_enrollment_id' => $enrollment->id,
                'from_group_id' => $enrollment->group_id,
                'to_group_id' => $target->id,
                'date' => $date->toDateString(),
                'reason' => $reason,
                'charges' => $chargesMode,
                'voided_charges' => $voided,
            ]);
            AlertEngine::evaluateFinanceForStudent((int) $enrollment->student_id);

            return $new;
        });
    }

    private function lockActive(Enrollment $enrollment): Enrollment
    {
        $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
        if ($locked->status !== 'active') {
            throw ValidationException::withMessages(['enrollment' => 'Esta inscripción ya no está activa.']);
        }

        return $locked;
    }

    private function assertCanJoin(Enrollment $enrollment, Group $target): void
    {
        if ($target->status !== 'active') {
            throw ValidationException::withMessages(['group_id' => 'No se puede inscribir en un grupo inactivo.']);
        }

        $activeSeats = Enrollment::query()->where('group_id', $target->id)->where('status', 'active');
        if ((clone $activeSeats)->where('student_id', $enrollment->student_id)->exists()) {
            throw ValidationException::withMessages(['group_id' => 'El alumno ya está inscrito en ese curso.']);
        }
        if ($target->capacity && (clone $activeSeats)->count() >= (int) $target->capacity) {
            throw ValidationException::withMessages(['group_id' => 'El grupo alcanzó su capacidad máxima.']);
        }

        RenewalEnrollmentEligibility::assertCanEnrollInGroup($enrollment->student, $target);
    }

    /** Anula los cargos de la consulta que no tienen ningún pago. Los abonados parcialmente se quedan. */
    private function voidUnpaid(Builder $query, string $reason, Request $request): int
    {
        $voided = 0;
        foreach ((clone $query)->whereNull('voided_at')->get() as $charge) {
            if (FinanceReconcile::paidTotalForCharge($charge) > 0) {
                continue;
            }
            $this->charges->void($charge, $request->user(), $reason, $request);
            $voided++;
        }

        return $voided;
    }

    private function markWithdrawn(Enrollment $enrollment, CarbonInterface $date, string $reason): void
    {
        $note = 'Retirado el '.$date->format('d/m/Y').': '.$reason;
        $notes = trim((string) $enrollment->notes);

        $enrollment->update([
            'status' => 'withdrawn',
            'notes' => $notes === '' ? $note : $notes."\n".$note,
        ]);
    }

    private function pendingCount(Enrollment $enrollment): int
    {
        return Charge::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereNull('voided_at')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->count();
    }
}
