<?php

namespace App\Mail;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** Resumen diario del profesor: sus clases de hoy, asistencias pendientes y recuperativas reservadas. #446 */
class TeacherDailyMail extends QueuedMailable
{
    /**
     * @param  Collection<int, \App\Models\ClassSession>  $todaySessions
     * @param  array<int, array{key: string, label: string, hint: string, count: int, url: string, action: string, tone: string}>  $items
     * @param  Collection<int, \App\Models\MakeupBooking>  $makeupBookings
     */
    public function __construct(
        public User $user,
        public Teacher $teacher,
        public Collection $todaySessions,
        public array $items,
        public Collection $makeupBookings,
    ) {
    }

    public function envelope(): Envelope
    {
        $classes = $this->todaySessions->count();

        return new Envelope(subject: 'Tu día en ON English: '.$classes.' '.($classes === 1 ? 'clase' : 'clases').' hoy');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.teacher-daily');
    }
}
