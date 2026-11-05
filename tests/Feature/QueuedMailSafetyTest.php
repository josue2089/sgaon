<?php

namespace Tests\Feature;

use App\Mail\ChargePendingMail;
use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\MakeupRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class QueuedMailSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Student $student;

    private Enrollment $enrollment;

    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);

        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => 'HS5A',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $campus->id, 'course_id' => $course->id, 'name' => 'HS5A', 'status' => 'active', 'capacity' => 20]);
        $this->student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'email' => 'ana@test.com', 'status' => 'active']);
        $this->enrollment = Enrollment::create(['campus_id' => $campus->id, 'student_id' => $this->student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $this->session = ClassSession::create(['campus_id' => $campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => today()->subDay()->toDateString(), 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
    }

    private function failingMailer(): void
    {
        // Falla al enviar, como una caída de Mailgun.
        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Mailgun caído');
            }

            public function __toString(): string
            {
                return 'failing';
            }
        });
        config(['mail.default' => 'failing', 'mail.mailers.failing' => ['transport' => 'failing']]);
    }

    private function markAbsent()
    {
        return $this->actingAs($this->admin)->post(route('attendance.store'), [
            'class_session_id' => $this->session->id,
            'records' => [['enrollment_id' => $this->enrollment->id, 'status' => 'absent']],
        ]);
    }

    private function pay()
    {
        $method = PaymentMethod::create(['currency' => PaymentMethod::CURRENCY_USD, 'method_type' => PaymentMethod::TYPE_ZELLE, 'label' => 'Zelle', 'email' => 'pay@test.com', 'is_active' => true, 'sort_order' => 1]);

        return $this->actingAs($this->admin)->post(route('finance.payments.store'), [
            'student_id' => $this->student->id, 'currency' => 'USD', 'payment_method_id' => $method->id,
            'paid_at' => today()->toDateString(), 'original_amount' => 10, 'reference' => 'ZL-1',
        ]);
    }

    public function test_family_mails_are_queued_and_credentials_are_not(): void
    {
        foreach (glob(app_path('Mail/*Mail.php')) as $file) {
            $class = 'App\\Mail\\'.basename($file, '.php');
            $this->assertSame($class !== \App\Mail\AdminCredentialsMail::class, is_subclass_of($class, ShouldQueue::class), $class);
        }
    }

    public function test_mail_is_queued_only_after_the_transaction_commits(): void
    {
        $charge = Charge::create(['campus_id' => $this->student->campus_id, 'student_id' => $this->student->id, 'concept' => 'Mensualidad', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => today(), 'status' => 'pending']);

        try {
            DB::transaction(function () use ($charge) {
                Mail::to('ana@test.com')->send(new ChargePendingMail($charge));
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        $this->assertSame(0, DB::table('jobs')->count());

        DB::transaction(function () use ($charge) {
            Mail::to('ana@test.com')->send(new ChargePendingMail($charge));
            $this->assertSame(0, DB::table('jobs')->count());
        });
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_attendance_and_payment_are_saved_when_the_mailer_fails(): void
    {
        $this->failingMailer();

        $this->markAbsent()->assertRedirect()->assertSessionHas('success');
        $this->pay()->assertRedirect()->assertSessionHas('success');

        $this->assertSame(AttendanceRecord::STATUS_ABSENT, AttendanceRecord::query()->sole()->status);
        $this->assertSame(1, MakeupRequest::query()->count());
        $this->assertNotNull(Payment::query()->sole()->receipt);
        $pending = DB::table('jobs')->count();
        $this->assertGreaterThanOrEqual(2, $pending);

        // El worker falla al enviar: el correo queda para reintentar y lo guardado no se toca.
        $this->artisan('queue:work', ['--once' => true])->assertSuccessful();
        $this->assertSame($pending, DB::table('jobs')->count());
        $this->assertSame(1, (int) DB::table('jobs')->max('attempts'));
        $this->assertSame(1, AttendanceRecord::query()->count());
    }

    public function test_attendance_is_all_or_nothing(): void
    {
        AttendanceRecord::saved(fn () => throw new RuntimeException('falla a mitad'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin)->post(route('attendance.store'), [
                'class_session_id' => $this->session->id,
                'topic' => 'Unidad 1',
                'records' => [['enrollment_id' => $this->enrollment->id, 'status' => 'absent']],
            ]);
            $this->fail('Debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('falla a mitad', $exception->getMessage());
        } finally {
            AttendanceRecord::flushEventListeners();
        }

        $this->assertSame(0, AttendanceRecord::query()->count());
        $this->assertNull($this->session->fresh()->topic);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_worker_sends_queued_mails_including_the_receipt_pdf(): void
    {
        $this->markAbsent()->assertSessionHasNoErrors();
        $this->pay()->assertSessionHasNoErrors();
        $this->assertGreaterThanOrEqual(2, DB::table('jobs')->count());

        $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $receipt = $messages->first(fn ($sent) => str_contains($sent->getOriginalMessage()->getSubject(), 'Recibo'));
        $this->assertNotNull($receipt);
        $this->assertNotEmpty($receipt->getOriginalMessage()->getAttachments());
        $this->assertSame('ana@test.com', $receipt->getOriginalMessage()->getTo()[0]->getAddress());
    }

    public function test_failed_mail_is_logged(): void
    {
        Log::spy();
        $charge = Charge::create(['campus_id' => $this->student->campus_id, 'student_id' => $this->student->id, 'concept' => 'Mensualidad', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => today(), 'status' => 'pending']);

        (new ChargePendingMail($charge))->to('ana@test.com')->failed(new RuntimeException('Mailgun caído'));

        Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context) => str_contains($message, 'ChargePendingMail')
            && $context['to'] === ['ana@test.com'] && $context['error'] === 'Mailgun caído');
    }
}
