<?php

namespace App\Mail;

use App\Models\ChargePaymentRequest;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ChargePaymentApprovedMail extends QueuedMailable
{
    public function __construct(public ChargePaymentRequest $paymentRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pago aprobado');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.charge-payment-approved');
    }
}
