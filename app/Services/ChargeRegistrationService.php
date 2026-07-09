<?php

namespace App\Services;

use App\Mail\ChargePendingMail;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Student;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\CampusScope;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ChargeRegistrationService
{
    public function register(array $data, ?Request $request = null): Charge
    {
        $enrollment = null;
        if (! empty($data['enrollment_id'])) {
            $enrollment = Enrollment::query()
                ->with(['group.course.period'])
                ->findOrFail((int) $data['enrollment_id']);

            if ($request && ! CampusScope::userCanAccessCampus($request->user(), (int) $enrollment->campus_id)) {
                abort(403);
            }

            $data['student_id'] = $enrollment->student_id;
            $data['campus_id'] = $enrollment->campus_id;
            $data['group_id'] = $enrollment->group_id;
            $data['course_id'] = $enrollment->group?->course_id;
            $data['period_id'] = $enrollment->group?->course?->period_id;
            $data['origin'] = 'manual';
            $data['billing_period_label'] = $data['billing_period_label'] ?: ($enrollment->group?->course?->period?->code ?? null);
        } else {
            $studentId = (int) ($data['student_id'] ?? 0);
            if (! $studentId) {
                throw ValidationException::withMessages([
                    'student_id' => 'Debes seleccionar un alumno o una inscripción.',
                ]);
            }

            $student = Student::findOrFail($studentId);
            if ($request && ! CampusScope::userCanAccessCampus($request->user(), (int) $student->campus_id)) {
                abort(403);
            }

            $data['campus_id'] = $student->campus_id;
            $data['origin'] = 'manual';
        }

        $data['currency'] = strtoupper((string) ($data['currency'] ?? PaymentCurrencyConverter::CURRENCY_USD));

        $charge = Charge::create($data);

        if ($request) {
            AuditTrail::log($request, 'finance.charge.create', $charge, $data);
        }

        AlertEngine::evaluateFinanceForStudent((int) $data['student_id']);
        $this->notifyChargePending($charge->fresh('student.representatives'));

        return $charge;
    }

    private function notifyChargePending(Charge $charge): void
    {
        $recipients = $this->recipientsForStudent($charge->student);
        if ($recipients->isEmpty()) {
            Log::info('Charge pending email skipped: no recipients', ['charge_id' => $charge->id]);

            return;
        }

        Mail::to($recipients->all())->send(new ChargePendingMail($charge));
    }

    private function recipientsForStudent(?Student $student): Collection
    {
        if (! $student) {
            return collect();
        }

        $student->loadMissing('representatives');

        return collect([
            $student->email,
            ...$student->representatives->pluck('email')->all(),
        ])->filter(fn ($email) => filled($email))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values();
    }
}
