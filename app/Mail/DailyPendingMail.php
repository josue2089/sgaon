<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Resumen diario de pendientes para un admin (mismo cálculo que el panel "Pendientes de hoy"). */
class DailyPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{key: string, label: string, hint: string, count: int, url: string, action: string, tone: string}>  $items
     */
    public function __construct(public User $user, public array $items, public string $scopeLabel)
    {
    }

    public function envelope(): Envelope
    {
        $total = collect($this->items)->sum('count');

        return new Envelope(subject: "Pendientes de hoy en ON English ({$total})");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-pending');
    }
}
