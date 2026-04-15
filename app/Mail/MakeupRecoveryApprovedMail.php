<?php

namespace App\Mail;

use App\Models\MakeupRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MakeupRecoveryApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MakeupRequest $makeupRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pago validado para recuperativa');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.makeup-recovery-approved');
    }

    public function attachments(): array
    {
        return [];
    }
}
