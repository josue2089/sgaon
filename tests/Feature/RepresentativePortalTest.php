<?php

namespace Tests\Feature;

use App\Mail\MakeupRecoveryBookedMail;
use App\Mail\MakeupRecoveryCreatedMail;
use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\Payment;
use App\Models\ProgramLevel;
use App\Models\Receipt;
use App\Models\Representative;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** #448: portal del representante con asistencia, recuperativas, recibos y avisos por cada hijo. */
class RepresentativePortalTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Student $student;

    private Student $otherStudent;

    private User $representativeUser;

    private User $otherRepresentativeUser;

    private Enrollment $enrollment;

    private ClassSession $classSession;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow('2026-10-07 10:00:00');

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active', 'contact_phone' => '0414-1234567', 'contact_email' => 'picacho@onenglish.test']);
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Baron', 'last_name' => 'Nava', 'status' => 'active']);
        $level = ProgramLevel::query()->firstOrFail();
        $this->course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher->id,
            'name' => 'HS5A Picacho',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $this->course->id, 'name' => 'G-HS5A', 'status' => 'active', 'capacity' => 30]);

        // Alumno sin correo propio: los avisos le llegan solo al representante.
        $this->student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Sergio', 'last_name' => 'León', 'status' => 'active']);
        $this->enrollment = Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $this->classSession = ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-10-05', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
        ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 2, 'session_date' => '2026-10-12', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);

        $this->representativeUser = User::factory()->create(['role' => 'representative', 'email' => 'marta@test.com', 'campus_id' => $this->campus->id]);
        Representative::create(['campus_id' => $this->campus->id, 'user_id' => $this->representativeUser->id, 'first_name' => 'Marta', 'last_name' => 'León', 'email' => 'marta@test.com'])
            ->students()->attach($this->student->id);

        $this->otherStudent = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Nora', 'last_name' => 'Paz', 'status' => 'active']);
        $this->otherRepresentativeUser = User::factory()->create(['role' => 'representative', 'email' => 'luis@test.com', 'campus_id' => $this->campus->id]);
        Representative::create(['campus_id' => $this->campus->id, 'user_id' => $this->otherRepresentativeUser->id, 'first_name' => 'Luis', 'last_name' => 'Paz', 'email' => 'luis@test.com'])
            ->students()->attach($this->otherStudent->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function absence(): MakeupRequest
    {
        $record = AttendanceRecord::create(['class_session_id' => $this->classSession->id, 'enrollment_id' => $this->enrollment->id, 'status' => 'absent']);

        return MakeupRecoveryEngine::syncForAttendanceRecord($record);
    }

    private function paymentWithReceipt(Student $student): Receipt
    {
        $payment = Payment::create(['campus_id' => $student->campus_id, 'student_id' => $student->id, 'amount' => 40, 'original_amount' => 40, 'currency' => 'USD', 'paid_at' => '2026-10-01', 'method' => 'Zelle', 'reference' => 'Z-1', 'status' => 'confirmed']);

        return Receipt::create(['campus_id' => $student->campus_id, 'payment_id' => $payment->id, 'receipt_number' => 'R-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT), 'issued_at' => '2026-10-01']);
    }

    public function test_absence_notice_reaches_the_representative_even_without_student_email(): void
    {
        $this->absence();

        Mail::assertQueued(MakeupRecoveryCreatedMail::class, fn ($mail) => $mail->hasTo('marta@test.com'));
        Mail::assertNotQueued(MakeupRecoveryCreatedMail::class, fn ($mail) => $mail->hasTo('luis@test.com'));
    }

    public function test_representative_sees_attendance_agenda_makeups_and_receipts_of_each_child(): void
    {
        $this->absence();
        $receipt = $this->paymentWithReceipt($this->student);

        $this->actingAs($this->representativeUser)->get(route('portal.representative'))
            ->assertOk()
            ->assertSee('Agenda y asistencia')
            ->assertSee('Resumen de asistencia')
            ->assertSee('12/10/2026')
            ->assertSee('Recuperativas')
            ->assertSee(route('portal.representative.makeups.payment', MakeupRequest::query()->sole()), false)
            ->assertSee(route('portal.representative.receipts.pdf', $receipt), false)
            ->assertSee('accept="image/*,.pdf"', false)
            ->assertDontSee('Nora');
    }

    public function test_representative_uploads_makeup_proof_from_the_phone_and_books_the_session(): void
    {
        $makeup = $this->absence();

        $this->actingAs($this->otherRepresentativeUser)
            ->post(route('portal.representative.makeups.payment', $makeup), ['payment_proof' => UploadedFile::fake()->image('pago.jpg')])
            ->assertForbidden();

        $this->actingAs($this->representativeUser)
            ->post(route('portal.representative.makeups.payment', $makeup), ['payment_proof' => UploadedFile::fake()->image('pago.jpg'), 'payment_notes' => 'Pago móvil 1234'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $this->assertSame(MakeupRequest::STATUS_PENDING_VALIDATION, $makeup->fresh()->status);

        // Un segundo comprobante mientras el primero está en validación se rechaza.
        $this->actingAs($this->representativeUser)
            ->post(route('portal.representative.makeups.payment', $makeup), ['payment_proof' => UploadedFile::fake()->image('otro.jpg')])
            ->assertSessionHasErrors('payment_proof');

        $makeup->forceFill(['status' => MakeupRequest::STATUS_APPROVED_FOR_BOOKING])->save();
        $session = MakeupSession::create(['campus_id' => $this->campus->id, 'teacher_id' => $this->course->teacher_id, 'program_id' => $this->course->program_id, 'program_level_id' => $this->course->program_level_id, 'session_date' => '2026-10-10', 'starts_at' => '10:00:00', 'ends_at' => '11:30:00', 'capacity' => 5, 'status' => 'open']);

        $this->actingAs($this->representativeUser)->get(route('portal.representative'))->assertSee($session->display_label);
        $this->actingAs($this->representativeUser)
            ->post(route('portal.representative.makeups.book', $makeup), ['makeup_session_id' => $session->id])
            ->assertSessionHas('success', 'Recuperativa reservada.');

        $this->assertSame(MakeupRequest::STATUS_BOOKED, $makeup->fresh()->status);
        Mail::assertQueued(MakeupRecoveryBookedMail::class, fn ($mail) => $mail->hasTo('marta@test.com'));
    }

    public function test_receipt_pdf_is_only_for_the_family_of_the_payment(): void
    {
        $receipt = $this->paymentWithReceipt($this->student);

        $this->actingAs($this->representativeUser)->get(route('portal.representative.receipts.pdf', $receipt))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->otherRepresentativeUser)->get(route('portal.representative.receipts.pdf', $receipt))
            ->assertForbidden();

        $studentUser = User::factory()->create(['role' => 'student', 'email' => 'sergio@test.com', 'campus_id' => $this->campus->id]);
        $this->student->update(['user_id' => $studentUser->id]);
        $this->actingAs($studentUser)->get(route('portal.student.receipts.pdf', $receipt))->assertOk();
        $this->actingAs($studentUser)->get(route('portal.student.receipts.pdf', $this->paymentWithReceipt($this->otherStudent)))->assertForbidden();
    }

    public function test_unlinked_account_and_error_pages_show_the_campus_contact(): void
    {
        $unlinked = User::factory()->create(['role' => 'representative', 'email' => 'nuevo@test.com', 'campus_id' => $this->campus->id]);

        $this->actingAs($unlinked)->get(route('portal.representative'))
            ->assertOk()
            ->assertSee('Tu cuenta todavía no está vinculada')
            ->assertSee('0414-1234567')
            ->assertSee('https://wa.me/584141234567', false);

        $this->actingAs($this->otherRepresentativeUser)->get(route('portal.representative.receipts.pdf', $this->paymentWithReceipt($this->student)))
            ->assertForbidden()
            ->assertSee('No tienes acceso a esta sección')
            ->assertSee('picacho@onenglish.test');

        $this->get('/esta-pagina-no-existe')
            ->assertNotFound()
            ->assertSee('No encontramos esta página');
    }

    public function test_master_saves_campus_contact(): void
    {
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);

        $this->actingAs($master)->put(route('campuses.update', $this->campus), [
            'name' => 'Picacho', 'code' => 'PIC', 'status' => 'active',
            'contact_phone' => '0424-7654321', 'contact_email' => 'coordinacion@onenglish.test',
        ])->assertSessionHasNoErrors();

        $this->assertSame('0424-7654321', $this->campus->fresh()->contact_phone);
        $this->assertSame('coordinacion@onenglish.test', $this->campus->fresh()->contact_email);
    }
}
