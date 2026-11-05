<?php

namespace App\Mail;

use App\Models\MakeupRequest;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MakeupRecoveryCreatedMail extends QueuedMailable
{
    public function __construct(public MakeupRequest $makeupRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recuperativa pendiente de pago');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.makeup-recovery-created');
    }

    public function attachments(): array
    {
        return [];
    }
}
