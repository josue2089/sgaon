<?php

namespace App\Mail;

use App\Models\PortalInvitation;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Lleva el enlace de activación: va cifrado en la cola para no dejar el token legible en la tabla jobs. */
class PortalInvitationMail extends QueuedMailable implements ShouldBeEncrypted
{
    public function __construct(
        public User $user,
        public string $url,
        public string $loginHint,
        public string $purpose = PortalInvitation::PURPOSE_INVITATION,
        public bool $sentToRepresentative = false,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === PortalInvitation::PURPOSE_RECOVERY
            ? 'Recupera tu acceso a ON English'
            : 'Activa tu acceso al portal de ON English');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.portal-invitation');
    }
}
