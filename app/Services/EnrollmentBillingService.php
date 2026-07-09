<?php

namespace App\Services;

use App\Mail\ChargePendingMail;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\PaymentCurrencyConverter;
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

        $programLevel = $course->programLevel;
        $basePriceEur = (float) ($programLevel?->resolvedBasePriceEur() ?? 0);
        if ($basePriceEur <= 0) {
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
            'amount' => $basePriceEur,
            'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
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
