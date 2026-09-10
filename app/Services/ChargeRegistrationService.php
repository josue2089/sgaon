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

            $context = $this->resolveAcademicContext($data, $student);
            $data['enrollment_id'] = $data['enrollment_id'] ?? $context['enrollment_id'];
            $data['group_id'] = $data['group_id'] ?? $context['group_id'];
            $data['course_id'] = $data['course_id'] ?? $context['course_id'];
            $data['period_id'] = $data['period_id'] ?? $context['period_id'];
            if (empty($data['billing_period_label']) && ! empty($context['billing_period_label'])) {
                $data['billing_period_label'] = $context['billing_period_label'];
            }
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

    /**
     * Deriva course_id, group_id, period_id y enrollment_id para cargos manuales
     * cuando el operador no envía enrollment_id pero el alumno tiene inscripciones.
     * Prioridad: request explícito → inscripción activa única del alumno.
     *
     * @param  array<string, mixed>  $data
     * @return array{enrollment_id: int|null, group_id: int|null, course_id: int|null, period_id: int|null, billing_period_label: string|null}
     */
    private function resolveAcademicContext(array $data, Student $student): array
    {
        $context = [
            'enrollment_id' => null,
            'group_id' => isset($data['group_id']) ? (int) $data['group_id'] : null,
            'course_id' => isset($data['course_id']) ? (int) $data['course_id'] : null,
            'period_id' => isset($data['period_id']) ? (int) $data['period_id'] : null,
            'billing_period_label' => null,
        ];

        $enrollment = Enrollment::query()
            ->with(['group.course.period'])
            ->where('student_id', $student->id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->first();

        if (! $enrollment) {
            return $context;
        }

        $context['enrollment_id'] = $enrollment->id;
        $context['group_id'] = $context['group_id'] ?: $enrollment->group_id;
        $context['course_id'] = $context['course_id'] ?: $enrollment->group?->course_id;
        $context['period_id'] = $context['period_id'] ?: $enrollment->group?->course?->period_id;
        $context['billing_period_label'] = $enrollment->group?->course?->period?->code;

        return $context;
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
