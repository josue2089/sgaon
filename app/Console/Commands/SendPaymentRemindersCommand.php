<?php

namespace App\Console\Commands;

use App\Mail\ChargeDueReminderMail;
use App\Models\Charge;
use App\Support\FinanceReconcile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'finance:send-payment-reminders';

    protected $description = 'Envía recordatorios de pago por correo según vencimiento y mora';

    public function handle(): int
    {
        $daysBefore = collect(config('finance.payment_reminder_days_before', [7, 3, 1]))
            ->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day >= 0)
            ->unique()
            ->values();

        $overdueInterval = max(1, (int) config('finance.overdue_reminder_interval_days', 7));
        $sent = 0;
        $today = now()->startOfDay();

        $charges = Charge::query()
            ->whereNull('voided_at')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereNotNull('due_date')
            ->with(['student.representatives'])
            ->get()
            ->filter(fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge) > 0);

        foreach ($charges as $charge) {
            $dueDate = $charge->due_date?->copy()->startOfDay();
            if (! $dueDate) {
                continue;
            }

            $reminderType = null;
            $daysUntilDue = $today->diffInDays($dueDate, false);

            if ($daysUntilDue < 0) {
                $daysOverdue = abs($daysUntilDue);
                if ($daysOverdue % $overdueInterval !== 0) {
                    continue;
                }
                $reminderType = 'overdue';
            } elseif ($daysUntilDue === 0) {
                $reminderType = 'due_today';
            } elseif ($daysBefore->contains($daysUntilDue)) {
                $reminderType = 'upcoming';
            }

            if (! $reminderType) {
                continue;
            }

            if ($charge->last_reminder_sent_at?->isToday()) {
                continue;
            }

            $recipients = $this->recipientsForCharge($charge);
            if ($recipients->isEmpty()) {
                continue;
            }

            Mail::to($recipients->all())->send(new ChargeDueReminderMail($charge, $reminderType));
            $charge->forceFill(['last_reminder_sent_at' => now()])->save();
            $sent++;
        }

        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }

    private function recipientsForCharge(Charge $charge)
    {
        return collect([
            $charge->student?->email,
            ...($charge->student?->representatives?->pluck('email')->all() ?? []),
        ])->filter(fn ($email) => filled($email))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values();
    }
}
