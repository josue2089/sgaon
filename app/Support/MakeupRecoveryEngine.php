<?php

namespace App\Support;

use App\Mail\MakeupRecoveryApprovedMail;
use App\Mail\MakeupRecoveryBookedMail;
use App\Mail\MakeupRecoveryCreatedMail;
use App\Mail\MakeupRecoveryRejectedMail;
use App\Models\Alert;
use App\Models\AttendanceRecord;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\MakeupBooking;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class MakeupRecoveryEngine
{
    public static function syncForAttendanceRecord(AttendanceRecord $record): ?MakeupRequest
    {
        $record->loadMissing([
            'enrollment.student',
            'enrollment.group.course.program',
            'enrollment.group.course.programLevel',
            'classSession.group.course',
        ]);

        if (! in_array($record->status, [AttendanceRecord::STATUS_ABSENT, AttendanceRecord::STATUS_JUSTIFIED], true)) {
            return null;
        }

        $enrollment = $record->enrollment;
        $student = $enrollment?->student;
        $course = $enrollment?->group?->course;
        $session = $record->classSession;

        if (! $enrollment || ! $student || ! $course || ! $session || ! $course->program_id || ! $course->program_level_id) {
            return null;
        }

        $request = MakeupRequest::firstOrNew([
            'attendance_record_id' => $record->id,
        ]);

        $request->fill([
            'campus_id' => $student->campus_id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'class_session_id' => $session->id,
            'request_type' => $record->status,
            'price' => $request->medical_support_required ? 5 : 10,
            'status' => $request->exists ? $request->status : MakeupRequest::STATUS_PENDING_PAYMENT,
        ]);
        $request->save();

        if (! $request->charge_id) {
            $charge = Charge::create([
                'campus_id' => $student->campus_id,
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'makeup_request_id' => $request->id,
                'course_id' => $course->id,
                'group_id' => $enrollment->group_id,
                'period_id' => $course->period_id,
                'concept' => 'Clase recuperativa · '.$course->name.' · '.($session->session_date?->format('d/m/Y') ?? 'sin fecha'),
                'charge_type' => 'makeup',
                'billing_period_label' => $course->period?->code,
                'origin' => 'makeup_recovery',
                'amount' => $request->price,
                'due_date' => now()->toDateString(),
                'status' => 'pending',
                'notes' => 'Generado automáticamente por inasistencia.',
            ]);

            $request->forceFill(['charge_id' => $charge->id])->save();
        } else {
            $charge = $request->charge;
            if ($charge) {
                $charge->update([
                    'amount' => $request->price,
                    'makeup_request_id' => $request->id,
                ]);
            }
        }

        self::openAlert($request);
        self::emailCreatedIfNeeded($request->fresh(['student', 'missedSession.group.course', 'charge', 'enrollment.group.course.programLevel']));

        return $request;
    }

    public static function refreshPriceAndCharge(MakeupRequest $request): void
    {
        $request->price = $request->medical_support_required ? 5 : 10;
        $request->save();

        if ($request->charge) {
            $request->charge->update([
                'amount' => $request->price,
                'notes' => $request->medical_support_required
                    ? 'Recuperativa con reposo validable.'
                    : 'Recuperativa estándar.',
            ]);
            FinanceReconcile::syncCharge($request->charge);
        }
    }

    public static function approve(MakeupRequest $request, array $paymentData = []): void
    {
        if ($request->payment_id || ! $request->charge) {
            $payment = $request->payment;
        } else {
            $paidAt = $paymentData['paid_at'] ?? now()->toDateString();
            $payment = Payment::create([
                'campus_id' => $request->campus_id,
                'student_id' => $request->student_id,
                'charge_id' => $request->charge_id,
                'makeup_request_id' => $request->id,
                'amount' => $request->price,
                'paid_at' => $paidAt,
                'paid_at_datetime' => now(),
                'method' => $paymentData['method'] ?? 'Comprobante validado',
                'reference' => $paymentData['reference'] ?? null,
                'status' => 'confirmed',
                'received_by' => $paymentData['received_by'] ?? null,
                'notes' => $paymentData['notes'] ?? 'Pago confirmado desde recuperativas.',
            ]);

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'charge_id' => $request->charge_id,
                'amount_applied' => $request->price,
            ]);

            FinanceReconcile::syncCharge($request->charge);

            Receipt::create([
                'campus_id' => $request->campus_id,
                'payment_id' => $payment->id,
                'receipt_number' => 'R-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
                'issued_at' => $paidAt,
            ]);
        }

        $request->forceFill([
            'payment_id' => $payment?->id,
            'status' => MakeupRequest::STATUS_APPROVED_FOR_BOOKING,
            'validated_at' => now(),
            'rejection_reason' => null,
        ])->save();

        self::syncAlertStatus($request->student_id);
        self::emailApprovedIfNeeded($request->fresh(['student', 'missedSession.group.course', 'enrollment.group.course.programLevel']));
    }

    public const REQUEST_TYPE_MANUAL = 'manual';

    /**
     * Registra una recuperativa a mano desde la ficha del alumno: solicitud (nueva o la de la inasistencia),
     * cargo con el precio indicado, bloque de recuperación con el profesor/fecha/horario y la reserva del alumno.
     *
     * @param  array{teacher_id: int, session_date: string, starts_at: string, ends_at: string, price: float, medical_support_required: bool, notes?: ?string}  $data
     */
    public static function registerManual(Enrollment $enrollment, ?AttendanceRecord $absence, array $data): MakeupRequest
    {
        $enrollment->loadMissing(['student', 'group.course.period']);
        $course = $enrollment->group?->course;
        if (! $course || ! $course->program_id || ! $course->program_level_id) {
            throw ValidationException::withMessages(['enrollment_id' => 'El curso del alumno no tiene programa y nivel configurados.']);
        }

        return DB::transaction(function () use ($enrollment, $absence, $data, $course): MakeupRequest {
            if ($absence) {
                $request = self::syncForAttendanceRecord($absence->fresh(['enrollment.student', 'enrollment.group.course', 'classSession']));
                if (! $request) {
                    throw ValidationException::withMessages(['attendance_record_id' => 'La clase elegida no está marcada como inasistencia.']);
                }
                if (in_array($request->status, [MakeupRequest::STATUS_BOOKED, MakeupRequest::STATUS_COMPLETED], true)) {
                    throw ValidationException::withMessages(['attendance_record_id' => 'Esa inasistencia ya tiene una recuperativa reservada o completada.']);
                }
            } else {
                $request = MakeupRequest::create([
                    'campus_id' => $enrollment->campus_id,
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                    'request_type' => self::REQUEST_TYPE_MANUAL,
                    'price' => $data['price'],
                    'medical_support_required' => $data['medical_support_required'],
                    'status' => MakeupRequest::STATUS_PENDING_PAYMENT,
                ]);
            }

            $request->forceFill([
                'price' => $data['price'],
                'medical_support_required' => $data['medical_support_required'],
            ])->save();

            $charge = $request->charge;
            if ($charge && FinanceReconcile::paidTotalForCharge($charge) <= 0) {
                $charge->update(['amount' => $data['price']]);
            } elseif (! $charge) {
                $charge = Charge::create([
                    'campus_id' => $request->campus_id,
                    'student_id' => $request->student_id,
                    'enrollment_id' => $enrollment->id,
                    'makeup_request_id' => $request->id,
                    'course_id' => $course->id,
                    'group_id' => $enrollment->group_id,
                    'period_id' => $course->period_id,
                    'concept' => 'Clase recuperativa · '.$course->name.' · '.Carbon::parse($data['session_date'])->format('d/m/Y'),
                    'charge_type' => 'makeup',
                    'billing_period_label' => $course->period?->code,
                    'origin' => 'makeup_manual',
                    'amount' => $data['price'],
                    'due_date' => Carbon::parse($data['session_date'])->toDateString(),
                    'status' => 'pending',
                    'notes' => 'Recuperativa registrada desde la ficha del alumno.',
                ]);
                $request->forceFill(['charge_id' => $charge->id])->save();
            }

            $session = MakeupSession::create([
                'campus_id' => $request->campus_id,
                'teacher_id' => $data['teacher_id'],
                'program_id' => $course->program_id,
                'program_level_id' => $course->program_level_id,
                'session_date' => $data['session_date'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'capacity' => 1,
                'status' => 'open',
                'notes' => $data['notes'] ?? 'Registrada desde la ficha del alumno.',
            ]);

            MakeupBooking::updateOrCreate(
                ['makeup_request_id' => $request->id],
                ['makeup_session_id' => $session->id, 'booked_at' => now(), 'status' => 'reserved'],
            );
            $request->forceFill(['status' => MakeupRequest::STATUS_BOOKED])->save();

            self::syncSessionStatus($session->fresh()->loadCount('activeBookings'));
            self::syncAlertStatus($request->student_id);
            FinanceReconcile::syncCharge($charge->fresh());

            return $request->fresh(['charge', 'booking.makeupSession.teacher']);
        });
    }

    /**
     * Alinea la solicitud de recuperativa con el estado de su cargo, venga el pago de donde venga
     * (Finanzas, ficha del alumno, portal). Devuelve el estado nuevo, o null si no hubo cambio.
     */
    public static function syncWithCharge(Charge $charge, bool $notify = true): ?string
    {
        $request = MakeupRequest::query()
            ->where('charge_id', $charge->id)
            ->when($charge->makeup_request_id, fn ($query) => $query->orWhere('id', $charge->makeup_request_id))
            ->first();
        if (! $request) {
            return null;
        }

        $awaitingPayment = [MakeupRequest::STATUS_PENDING_PAYMENT, MakeupRequest::STATUS_PENDING_VALIDATION];

        if ($charge->voided_at) {
            if (! in_array($request->status, [...$awaitingPayment, MakeupRequest::STATUS_APPROVED_FOR_BOOKING], true)) {
                return null;
            }
            $request->forceFill(['status' => MakeupRequest::STATUS_CANCELLED])->save();
            self::syncAlertStatus($request->student_id);

            return MakeupRequest::STATUS_CANCELLED;
        }

        if ($charge->status === 'paid' && in_array($request->status, $awaitingPayment, true)) {
            $payment = Payment::query()
                ->whereNull('voided_at')
                ->where(fn ($query) => $query
                    ->where('charge_id', $charge->id)
                    ->orWhereHas('allocations', fn ($allocations) => $allocations->where('charge_id', $charge->id)))
                ->latest('id')
                ->first();

            $request->forceFill([
                'payment_id' => $payment?->id,
                'status' => MakeupRequest::STATUS_APPROVED_FOR_BOOKING,
                'validated_at' => now(),
                'rejection_reason' => null,
            ])->save();
            self::syncAlertStatus($request->student_id);
            if ($notify) {
                self::emailApprovedIfNeeded($request->fresh(['student', 'missedSession.group.course', 'enrollment.group.course.programLevel']));
            }

            return MakeupRequest::STATUS_APPROVED_FOR_BOOKING;
        }

        // Pago anulado: si todavía no reservó, vuelve a esperar el pago.
        if ($charge->status !== 'paid'
            && $request->status === MakeupRequest::STATUS_APPROVED_FOR_BOOKING
            && ! $request->booking()->exists()) {
            $request->forceFill([
                'payment_id' => null,
                'status' => MakeupRequest::STATUS_PENDING_PAYMENT,
                'validated_at' => null,
            ])->save();
            self::syncAlertStatus($request->student_id);

            return MakeupRequest::STATUS_PENDING_PAYMENT;
        }

        return null;
    }

    public static function reject(MakeupRequest $request, ?string $reason = null): void
    {
        $request->forceFill([
            'status' => MakeupRequest::STATUS_REJECTED,
            'validated_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        self::syncAlertStatus($request->student_id);
        self::emailRejectedIfNeeded($request->fresh(['student.representatives', 'missedSession.group.course', 'enrollment.group.course.programLevel']));
    }

    public static function book(MakeupRequest $request, MakeupSession $session): MakeupBooking
    {
        $request->loadMissing(['enrollment.group.course.programLevel', 'student']);
        $session->loadCount('activeBookings');

        if ($request->status !== MakeupRequest::STATUS_APPROVED_FOR_BOOKING) {
            abort(422, 'La solicitud no está lista para reserva.');
        }

        $course = $request->enrollment?->group?->course;
        if (! $course || (int) $session->campus_id !== (int) $request->campus_id
            || (int) $session->program_id !== (int) $course->program_id
            || (int) $session->program_level_id !== (int) $course->program_level_id) {
            abort(422, 'La recuperativa no es compatible con el alumno.');
        }

        if ($session->available_slots <= 0 || ! in_array($session->status, ['open', 'full'], true)) {
            abort(422, 'No hay cupos disponibles en este bloque.');
        }

        $booking = MakeupBooking::updateOrCreate(
            ['makeup_request_id' => $request->id],
            [
                'makeup_session_id' => $session->id,
                'booked_at' => now(),
                'status' => 'reserved',
            ]
        );

        $request->update(['status' => MakeupRequest::STATUS_BOOKED]);
        self::syncSessionStatus($session->fresh()->loadCount('activeBookings'));
        self::syncAlertStatus($request->student_id);
        self::emailBookedIfNeeded($request->fresh(['student', 'missedSession.group.course', 'booking.makeupSession.teacher']));

        return $booking;
    }

    public static function updateBookingStatus(MakeupBooking $booking, string $status, ?string $notes = null): void
    {
        $booking->update([
            'status' => $status,
            'attended_at' => $status === 'attended' ? now() : null,
            'notes' => $notes,
        ]);

        $request = $booking->makeupRequest;
        if ($request) {
            $request->update([
                'status' => match ($status) {
                    'attended' => MakeupRequest::STATUS_COMPLETED,
                    'missed' => MakeupRequest::STATUS_MISSED,
                    'cancelled' => MakeupRequest::STATUS_APPROVED_FOR_BOOKING,
                    default => MakeupRequest::STATUS_BOOKED,
                },
            ]);
            self::syncAlertStatus($request->student_id);
        }

        self::syncSessionStatus($booking->makeupSession?->fresh()->loadCount('activeBookings'));
    }

    public static function syncSessionStatus(?MakeupSession $session): void
    {
        if (! $session) {
            return;
        }

        $status = $session->available_slots <= 0 ? 'full' : 'open';
        if (in_array($session->status, ['cancelled', 'completed'], true)) {
            return;
        }

        $session->update(['status' => $status]);
    }

    private static function openAlert(MakeupRequest $request): void
    {
        Alert::updateOrCreate(
            [
                'campus_id' => $request->campus_id,
                'student_id' => $request->student_id,
                'type' => 'makeup_recovery',
                'status' => 'open',
            ],
            [
                'message' => 'Tiene una clase recuperativa pendiente por gestionar.',
                'resolved_at' => null,
            ]
        );
    }

    private static function syncAlertStatus(int $studentId): void
    {
        $hasOpenActionable = MakeupRequest::query()
            ->where('student_id', $studentId)
            ->whereIn('status', [
                MakeupRequest::STATUS_PENDING_PAYMENT,
                MakeupRequest::STATUS_PENDING_VALIDATION,
                MakeupRequest::STATUS_APPROVED_FOR_BOOKING,
            ])
            ->exists();

        if ($hasOpenActionable) {
            return;
        }

        Alert::query()
            ->where('student_id', $studentId)
            ->where('type', 'makeup_recovery')
            ->where('status', 'open')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }

    private static function emailCreatedIfNeeded(MakeupRequest $request): void
    {
        if ($request->notification_emailed_at || empty($request->student?->email)) {
            return;
        }

        Mail::to($request->student->email)->send(new MakeupRecoveryCreatedMail($request));
        $request->forceFill(['notification_emailed_at' => now()])->save();
    }

    private static function emailApprovedIfNeeded(MakeupRequest $request): void
    {
        if ($request->approval_emailed_at) {
            return;
        }

        $recipients = self::notificationRecipients($request);
        if ($recipients->isEmpty()) {
            return;
        }

        Mail::to($recipients->all())->send(new MakeupRecoveryApprovedMail($request));
        $request->forceFill(['approval_emailed_at' => now()])->save();
    }

    private static function emailRejectedIfNeeded(MakeupRequest $request): void
    {
        if ($request->rejection_emailed_at) {
            return;
        }

        $recipients = self::notificationRecipients($request);
        if ($recipients->isEmpty()) {
            return;
        }

        Mail::to($recipients->all())->send(new MakeupRecoveryRejectedMail($request));
        $request->forceFill(['rejection_emailed_at' => now()])->save();
    }

    private static function emailBookedIfNeeded(MakeupRequest $request): void
    {
        if ($request->booking_emailed_at || empty($request->student?->email)) {
            return;
        }

        Mail::to($request->student->email)->send(new MakeupRecoveryBookedMail($request));
        $request->forceFill(['booking_emailed_at' => now()])->save();
    }

    private static function notificationRecipients(MakeupRequest $request)
    {
        $request->loadMissing(['student.representatives']);

        return collect([
            $request->student?->email,
            ...$request->student?->representatives?->pluck('email')->all() ?? [],
        ])
            ->filter(fn ($email) => filled($email))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values();
    }
}
