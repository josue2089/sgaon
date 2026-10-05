<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos de la "Planilla de inscripción — Actividades extracurriculares" de un alumno.
 */
class ExtracurricularSheet
{
    public const TERMS = [
        'El representante declara estar conforme con las normas de convivencia y funcionamiento de las actividades extracurriculares.',
        'Los pagos correspondientes deben realizarse dentro de los primeros cinco (5) días de cada mes para garantizar la continuidad del alumno en el programa.',
        'La inasistencia a las clases no exonera al representante del pago de la mensualidad correspondiente, salvo casos justificados evaluados por la coordinación.',
    ];

    public static function enrollmentFor(Student $student): ?Enrollment
    {
        return $student->enrollments()
            ->with(['group.course.program', 'group.course.programLevel.program', 'group.course.teacher', 'group.course.scheduleTemplate', 'group.course.campus'])
            ->whereHas('group.course.program', fn ($query) => $query->where('is_extracurricular', true))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(Student $student, Enrollment $enrollment): array
    {
        $student->loadMissing(['campus', 'representatives', 'authorizedContacts']);
        $course = $enrollment->group->course;
        $campus = $course->campus ?? $student->campus;

        return [
            'student' => $student,
            'enrollment' => $enrollment,
            'course' => $course,
            'representative' => $student->representatives->first(),
            'authorizedContact' => $student->authorizedContacts->sortBy('slot')->first(),
            'activity' => $course->program?->name ?? $course->name,
            'schedule' => $course->scheduleTemplate
                ? preg_replace('/(\d{1,2}:\d{2}):\d{2}/', '$1', $course->scheduleTemplate->display_label)
                : null,
            'teacher' => $course->teacher?->full_name,
            'campusName' => $campus?->name,
            'campusLogo' => $campus?->logoDataUri(),
            'months' => self::monthlyControl($enrollment),
            'terms' => self::TERMS,
        ];
    }

    /**
     * Una fila por mes del curso con el estado real de la cuota.
     *
     * @return Collection<int, array{month: string, label: string, status: string, paid_at: ?string, reference: ?string, amount: ?string}>
     */
    public static function monthlyControl(Enrollment $enrollment): Collection
    {
        $course = $enrollment->group->course;
        $start = collect([$course->start_date, $enrollment->enrolled_at ? Carbon::parse($enrollment->enrolled_at) : null])->filter()->max();
        $end = $course->end_date;
        if (! $start || ! $end) {
            return collect();
        }

        $charges = Charge::query()
            ->with(['paymentAllocations.payment.receipt', 'payments.receipt'])
            ->where('enrollment_id', $enrollment->id)
            ->where('charge_type', 'tuition')
            ->whereNull('voided_at')
            ->get()
            ->keyBy('billing_period_label');

        $rows = collect();
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();
        while ($cursor->lte($last)) {
            $key = $cursor->format('Y-m');
            $charge = $charges->get($key);
            $payment = $charge ? self::latestPayment($charge) : null;

            $rows->push([
                'month' => $key,
                'label' => ucfirst($cursor->copy()->locale('es')->translatedFormat('F')),
                'status' => self::statusLabel($charge),
                'paid_at' => $payment?->paid_at?->format('d/m/Y'),
                'reference' => $payment ? trim(($payment->reference ?: '').($payment->receipt ? ' · '.$payment->receipt->receipt_number : ''), ' ·') : null,
                'amount' => $charge ? MoneyFormat::formatLedgerAmount((float) $charge->amount, $charge->currency) : null,
            ]);
            $cursor->addMonth();
        }

        return $rows;
    }

    private static function statusLabel(?Charge $charge): string
    {
        if (! $charge) {
            return 'Por generar';
        }

        return match ($charge->status) {
            'paid' => 'Pagado',
            'partial' => 'Abonado',
            'overdue' => 'Vencido',
            default => 'Pendiente',
        };
    }

    private static function latestPayment(Charge $charge): ?Payment
    {
        return $charge->paymentAllocations->pluck('payment')
            ->merge($charge->payments)
            ->filter(fn (?Payment $payment) => $payment && ! $payment->voided_at)
            ->sortByDesc(fn (Payment $payment) => [$payment->paid_at?->timestamp ?? 0, $payment->id])
            ->first();
    }
}
