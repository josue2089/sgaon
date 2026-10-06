<?php

namespace App\Mail;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ChargeScheduleMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Charge>  $charges
     */
    public function __construct(public Enrollment $enrollment, public Collection $charges)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Calendario de cuotas de la actividad extracurricular');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.charge-schedule');
    }
}
