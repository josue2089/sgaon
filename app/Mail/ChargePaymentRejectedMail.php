<?php

namespace App\Mail;

use App\Models\ChargePaymentRequest;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ChargePaymentRejectedMail extends QueuedMailable
{
    public function __construct(public ChargePaymentRequest $paymentRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pago rechazado');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.charge-payment-rejected');
    }
}
