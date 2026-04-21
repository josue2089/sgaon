<?php

namespace App\Mail;

use App\Models\Charge;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChargePendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Charge $charge)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nuevo cargo pendiente de pago');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.charge-pending');
    }
}
