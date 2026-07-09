<?php

namespace App\Mail;

use App\Models\Charge;
use App\Services\Bcv\ExchangeRateService;
use App\Support\FinanceReconcile;
use App\Support\MoneyFormat;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChargeDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Charge $charge, public string $reminderType = 'upcoming')
    {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->reminderType) {
            'due_today' => 'Recordatorio: pago vence hoy',
            'overdue' => 'Recordatorio: pago vencido',
            default => 'Recordatorio: pago próximo a vencer',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $eurVesRate = 0.0;
        if ($this->charge->isEur()) {
            $eurVesRate = (float) (app(ExchangeRateService::class)->getLatestEurRate()['rate'] ?? 0);
        }

        return new Content(view: 'emails.charge-due-reminder', with: [
            'charge' => $this->charge,
            'outstanding' => FinanceReconcile::outstandingForCharge($this->charge),
            'amountLabel' => MoneyFormat::chargeAmount($this->charge, $eurVesRate > 0 ? $eurVesRate : null),
            'outstandingLabel' => $this->charge->isEur()
                ? MoneyFormat::eur(FinanceReconcile::outstandingForCharge($this->charge))
                : MoneyFormat::usd(FinanceReconcile::outstandingForCharge($this->charge)),
            'reminderType' => $this->reminderType,
        ]);
    }
}
