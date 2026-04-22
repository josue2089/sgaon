<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LevelRenewalReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public Course $course,
        public $currentLevel,
        public $nextLevel,
        public ?Course $nextCourse = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio de inscripcion al siguiente nivel',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.level-renewal-reminder',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
