<?php

namespace App\Mail;

use App\Models\MakeupRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MakeupRecoveryBookedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MakeupRequest $makeupRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recuperativa reservada');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.makeup-recovery-booked');
    }

    public function attachments(): array
    {
        return [];
    }
}
