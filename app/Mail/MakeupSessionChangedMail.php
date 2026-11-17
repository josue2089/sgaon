<?php

namespace App\Mail;

use App\Models\MakeupRequest;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** #452: aviso a quien tenía reservado un bloque recuperativo que se canceló o cambió de fecha u hora. */
class MakeupSessionChangedMail extends QueuedMailable
{
    /**
     * @param  'cancelled'|'rescheduled'  $change
     * @param  array{date: ?string, starts_at: ?string, ends_at: ?string}  $before
     */
    public function __construct(public MakeupRequest $makeupRequest, public string $change, public array $before = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->change === 'cancelled' ? 'Recuperativa cancelada' : 'Cambio de horario de la recuperativa');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.makeup-session-changed');
    }

    public function attachments(): array
    {
        return [];
    }
}
