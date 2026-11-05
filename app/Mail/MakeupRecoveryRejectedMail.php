<?php

namespace App\Mail;

use App\Models\MakeupRequest;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MakeupRecoveryRejectedMail extends QueuedMailable
{
    public function __construct(public MakeupRequest $makeupRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Comprobante de recuperativa rechazado');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.makeup-recovery-rejected');
    }

    public function attachments(): array
    {
        return [];
    }
}
