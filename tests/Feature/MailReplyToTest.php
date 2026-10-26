<?php

namespace Tests\Feature;

use App\Mail\ChargeDueReminderMail;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailReplyToTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_emails_reply_to_on_english(): void
    {
        config(['mail.default' => 'array']);
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'status' => 'active']);
        $charge = Charge::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'concept' => 'Mensualidad', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => '2026-11-05', 'status' => 'pending']);

        Mail::to('representante@test.com')->send(new ChargeDueReminderMail($charge));

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $replyTo = $message->getReplyTo();
        $this->assertCount(1, $replyTo);
        $this->assertSame('onenglishweb@gmail.com', $replyTo[0]->getAddress());
        $this->assertSame('ON English', $replyTo[0]->getName());
        $this->assertSame(config('mail.from.address'), $message->getFrom()[0]->getAddress());
    }
}
