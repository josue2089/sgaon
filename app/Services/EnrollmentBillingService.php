<?php

namespace App\Services;

use App\Mail\ChargePendingMail;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\TuitionPriceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnrollmentBillingService
{
    public function createTuitionCharge(Enrollment $enrollment, ?Request $request = null): ?Charge
    {
        $enrollment->loadMissing(['group.course.programLevel.program', 'group.course.period', 'student.representatives']);

        $course = $enrollment->group?->course;
        if (! $course) {
            return null;
        }

        // Extracurricular: una cuota por mes; al inscribir se crea la del mes en curso.
        if ($course->isExtracurricular()) {
            $month = $this->firstBillableMonth($enrollment, $course);

            return $month && $month->lte(now()->startOfMonth())
                ? $this->createMonthlyCharge($enrollment, now()->startOfMonth(), $request)
                : null;
        }

        $programLevel = $course->programLevel;
        $price = TuitionPriceResolver::forCourse($course, (int) $enrollment->campus_id);
        if (! $price) {
            return null;
        }

        $existingCharge = Charge::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('charge_type', 'tuition')
            ->whereNull('voided_at')
            ->whereIn('status', ['pending', 'partial', 'overdue', 'paid'])
            ->first();

        if ($existingCharge) {
            return null;
        }

        $dueDate = $this->resolveDueDate($enrollment, $course);
        $levelName = $programLevel?->name ?? $course->name;
        $concept = trim('Mensualidad '.$levelName.' — '.$course->name);

        $charge = Charge::create([
            'campus_id' => $enrollment->campus_id,
            'student_id' => $enrollment->student_id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'group_id' => $enrollment->group_id,
            'period_id' => $course->period_id,
            'concept' => $concept,
            'charge_type' => 'tuition',
            'billing_period_label' => $course->period?->code,
            'origin' => 'enrollment_auto',
            'amount' => $price['amount'],
            'currency' => $price['currency'],
            'due_date' => $dueDate,
            'status' => 'pending',
        ]);

        if ($request) {
            AuditTrail::log($request, 'finance.charge.create', $charge, $charge->toArray());
        }

        AlertEngine::evaluateFinanceForStudent((int) $enrollment->student_id);
        $this->emailChargePendingIfPossible($charge->fresh('student.representatives'));

        return $charge;
    }

    /**
     * Cuota mensual de un curso extracurricular para el mes indicado. Idempotente por inscripción y mes:
     * devuelve null si ya existe (no anulada) o si el mes cae fuera del curso.
     */
    public function createMonthlyCharge(Enrollment $enrollment, Carbon $month, ?Request $request = null): ?Charge
    {
        $enrollment->loadMissing(['group.course.programLevel.program', 'group.course.program', 'student.representatives']);
        $course = $enrollment->group?->course;
        if (! $course || ! $course->isExtracurricular() || $enrollment->status !== 'active') {
            return null;
        }

        $month = $month->copy()->startOfMonth();
        $firstMonth = $this->firstBillableMonth($enrollment, $course);
        $lastMonth = $course->end_date?->copy()->startOfMonth();
        if (! $firstMonth || $month->lt($firstMonth) || ($lastMonth && $month->gt($lastMonth))) {
            return null;
        }

        $price = TuitionPriceResolver::forCourse($course, (int) $enrollment->campus_id);
        if (! $price) {
            return null;
        }

        $monthKey = $month->format('Y-m');
        $exists = Charge::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('charge_type', 'tuition')
            ->where('billing_period_label', $monthKey)
            ->whereNull('voided_at')
            ->exists();
        if ($exists) {
            return null;
        }

        // Vence el día 5; si la inscripción es posterior, vence el día de la inscripción.
        $dueDate = $month->copy()->day(5);
        $enrolledAt = $enrollment->enrolled_at ? Carbon::parse($enrollment->enrolled_at)->startOfDay() : null;
        if ($enrolledAt && $enrolledAt->gt($dueDate) && $enrolledAt->isSameMonth($month)) {
            $dueDate = $enrolledAt;
        }

        $programName = $course->program?->name ?? $course->programLevel?->program?->name ?? $course->name;
        $monthLabel = ucfirst($month->copy()->locale('es')->translatedFormat('F Y'));

        $charge = Charge::create([
            'campus_id' => $enrollment->campus_id,
            'student_id' => $enrollment->student_id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'group_id' => $enrollment->group_id,
            'period_id' => $course->period_id,
            'concept' => 'Mensualidad '.$programName.' — '.$monthLabel,
            'charge_type' => 'tuition',
            'billing_period_label' => $monthKey,
            'origin' => 'monthly_auto',
            'amount' => $price['amount'],
            'currency' => $price['currency'],
            'due_date' => $dueDate->toDateString(),
            'status' => 'pending',
        ]);

        if ($request) {
            AuditTrail::log($request, 'finance.charge.create', $charge, $charge->toArray());
        }

        AlertEngine::evaluateFinanceForStudent((int) $enrollment->student_id);
        $this->emailChargePendingIfPossible($charge->fresh('student.representatives'));

        return $charge;
    }

    /**
     * Primer mes que se cobra: el más tardío entre el inicio del curso y la inscripción.
     */
    private function firstBillableMonth(Enrollment $enrollment, $course): ?Carbon
    {
        $starts = collect([
            $course->start_date?->copy(),
            $enrollment->enrolled_at ? Carbon::parse($enrollment->enrolled_at) : null,
        ])->filter();

        return $starts->isEmpty() ? null : $starts->max()->copy()->startOfMonth();
    }

    private function resolveDueDate(Enrollment $enrollment, $course): Carbon
    {
        if ($course->start_date) {
            return $course->start_date->copy();
        }

        $enrolledAt = $enrollment->enrolled_at
            ? Carbon::parse($enrollment->enrolled_at)
            : now();

        return $enrolledAt->copy()->addDays((int) config('finance.enrollment_due_days', 30));
    }

    private function emailChargePendingIfPossible(Charge $charge): void
    {
        $recipients = collect([
            $charge->student?->email,
            ...($charge->student?->representatives?->pluck('email')->all() ?? []),
        ])->filter(fn ($email) => filled($email))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            Log::info('Enrollment billing charge created without email recipients', ['charge_id' => $charge->id]);

            return;
        }

        Mail::to($recipients->all())->send(new ChargePendingMail($charge));
    }
}
