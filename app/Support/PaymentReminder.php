<?php

namespace App\Support;

use App\Mail\ChargeDueReminderMail;
use App\Models\Charge;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Recordatorio de pago por correo al alumno y sus representantes.
 * Lo usan el envío automático (finance:send-payment-reminders) y el envío manual en lote desde Alumnos (#450).
 */
final class PaymentReminder
{
    /** Días hacia adelante que cubre el recordatorio manual. */
    public const MANUAL_WINDOW_DAYS = 7;

    /** @return Collection<int, string> */
    public static function recipients(Charge $charge): Collection
    {
        return collect([
            $charge->student?->email,
            ...($charge->student?->representatives?->pluck('email')->all() ?? []),
        ])->filter(fn ($email) => filled($email))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values();
    }

    public static function typeFor(Charge $charge): string
    {
        $days = now()->startOfDay()->diffInDays($charge->due_date->copy()->startOfDay(), false);

        return $days < 0 ? 'overdue' : ($days === 0 ? 'due_today' : 'upcoming');
    }

    /** Envía el recordatorio de un cargo y marca la fecha. Devuelve false si no hay a quién enviarlo. */
    public static function send(Charge $charge, string $type): bool
    {
        $recipients = self::recipients($charge);
        if ($recipients->isEmpty()) {
            return false;
        }

        Mail::to($recipients->all())->send(new ChargeDueReminderMail($charge, $type));
        $charge->forceFill(['last_reminder_sent_at' => now()])->save();

        return true;
    }

    /**
     * Recordatorio manual: cargos con saldo vencidos o que vencen en los próximos 7 días, sin recordatorio hoy.
     *
     * @return array{sent: int, no_email: int}
     */
    public static function sendForStudent(Student $student): array
    {
        $result = ['sent' => 0, 'no_email' => 0];
        $charges = Charge::query()
            ->where('student_id', $student->id)
            ->whereNull('voided_at')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->addDays(self::MANUAL_WINDOW_DAYS)->toDateString())
            ->with('student.representatives')
            ->get()
            ->filter(fn (Charge $charge) => ! $charge->last_reminder_sent_at?->isToday() && FinanceReconcile::outstandingForCharge($charge) > 0);

        foreach ($charges as $charge) {
            self::send($charge, self::typeFor($charge)) ? $result['sent']++ : $result['no_email']++;
        }

        return $result;
    }
}
