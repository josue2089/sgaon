<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\PaymentMethod;
use App\Models\Representative;
use App\Models\ReportExport;
use App\Models\Student;
use App\Models\StudentAttachment;
use App\Models\User;
use App\Support\PrivateFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFilesTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Student $student;

    private User $studentUser;

    private User $representativeUser;

    private Charge $charge;

    private PaymentMethod $zelle;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateFiles::DISK);
        Storage::fake(PrivateFiles::LEGACY_DISK);

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->studentUser = User::factory()->create(['role' => 'student', 'email' => 'ana@test.com', 'campus_id' => $this->campus->id]);
        $this->student = Student::create(['campus_id' => $this->campus->id, 'user_id' => $this->studentUser->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'email' => 'ana@test.com', 'status' => 'active']);
        $this->representativeUser = User::factory()->create(['role' => 'representative', 'email' => 'marta@test.com', 'campus_id' => $this->campus->id]);
        Representative::create(['campus_id' => $this->campus->id, 'user_id' => $this->representativeUser->id, 'first_name' => 'Marta', 'last_name' => 'Gil', 'email' => 'marta@test.com'])
            ->students()->attach($this->student->id);
        $this->charge = Charge::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'concept' => 'Mensualidad', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => now()->addDays(5), 'status' => 'pending']);
        $this->zelle = PaymentMethod::create(['currency' => PaymentMethod::CURRENCY_USD, 'method_type' => PaymentMethod::TYPE_ZELLE, 'label' => 'Zelle', 'email' => 'pay@test.com', 'is_active' => true, 'sort_order' => 1]);
    }

    private function submitProof(UploadedFile $file)
    {
        return $this->actingAs($this->studentUser)->post(route('portal.student.charges.payment', $this->charge), [
            'currency' => 'USD', 'original_amount' => 40, 'payment_method_id' => $this->zelle->id, 'reference' => 'ZL-1', 'payment_proof' => $file,
        ]);
    }

    public function test_proof_is_stored_privately_and_served_only_to_allowed_users(): void
    {
        $this->submitProof(UploadedFile::fake()->image('proof.jpg'))->assertSessionHasNoErrors();
        $request = ChargePaymentRequest::query()->sole();

        Storage::disk(PrivateFiles::DISK)->assertExists($request->proof_path);
        Storage::disk(PrivateFiles::LEGACY_DISK)->assertMissing($request->proof_path);

        $url = route('files.payment-proof', $request);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->actingAs($admin)->get($url)->assertOk()->assertHeader('Cache-Control');
        $this->actingAs($this->studentUser)->get($url)->assertOk();
        $this->actingAs($this->representativeUser)->get($url)->assertOk();

        $stranger = User::factory()->create(['role' => 'student', 'email' => 'otro@test.com', 'campus_id' => $this->campus->id]);
        Student::create(['campus_id' => $this->campus->id, 'user_id' => $stranger->id, 'first_name' => 'Otro', 'last_name' => 'Alumno', 'status' => 'active']);
        $this->actingAs($stranger)->get($url)->assertForbidden();

        $otherCampus = Campus::create(['name' => 'Otra', 'code' => 'OTR', 'status' => 'active']);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'campus_id' => $otherCampus->id, 'is_master' => false]);
        $this->actingAs($otherAdmin)->get($url)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_rejects_non_image_or_pdf_files_without_storing_them(): void
    {
        $this->submitProof(UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))->assertSessionHasErrors('payment_proof');

        $this->assertSame(0, ChargePaymentRequest::query()->count());
        $this->assertSame([], Storage::disk(PrivateFiles::DISK)->allFiles());
    }

    public function test_legacy_public_file_is_still_served_until_moved(): void
    {
        Storage::disk(PrivateFiles::LEGACY_DISK)->put('charge-payment-requests/1/old.jpg', 'old');
        $request = ChargePaymentRequest::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'charge_id' => $this->charge->id, 'amount' => 40, 'currency' => 'USD', 'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION, 'submitted_at' => now(), 'proof_path' => 'charge-payment-requests/1/old.jpg']);

        $this->actingAs($this->representativeUser)->get(route('files.payment-proof', $request))->assertOk();
    }

    public function test_move_command_has_dry_run_and_moves_files(): void
    {
        $public = Storage::disk(PrivateFiles::LEGACY_DISK);
        $private = Storage::disk(PrivateFiles::DISK);
        $public->put('charge-payment-requests/1/a.jpg', 'proof');
        $public->put('student-attachments/1/cedula.pdf', 'doc');
        $public->put('profiles/students/photo.jpg', 'photo');
        ChargePaymentRequest::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'charge_id' => $this->charge->id, 'amount' => 40, 'currency' => 'USD', 'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION, 'submitted_at' => now(), 'proof_path' => 'charge-payment-requests/1/a.jpg']);
        StudentAttachment::create(['student_id' => $this->student->id, 'title' => 'Cédula', 'category' => 'general', 'file_path' => 'student-attachments/1/cedula.pdf', 'original_name' => 'cedula.pdf']);

        $this->artisan('files:move-private', ['--dry-run' => true])->expectsOutputToContain('[dry-run]')->assertSuccessful();
        $public->assertExists('charge-payment-requests/1/a.jpg');
        $private->assertMissing('charge-payment-requests/1/a.jpg');

        $this->artisan('files:move-private')->expectsOutputToContain('Archivos movidos al disco privado: 2.')->assertSuccessful();
        $public->assertMissing('charge-payment-requests/1/a.jpg');
        $public->assertMissing('student-attachments/1/cedula.pdf');
        $private->assertExists('charge-payment-requests/1/a.jpg');
        $private->assertExists('student-attachments/1/cedula.pdf');
        $this->assertSame('proof', $private->get('charge-payment-requests/1/a.jpg'));
        $public->assertExists('profiles/students/photo.jpg');

        $this->artisan('files:move-private')->expectsOutputToContain('Archivos movidos al disco privado: 0.')->assertSuccessful();
    }

    public function test_report_export_is_written_and_downloaded_from_private_disk(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        Storage::disk(PrivateFiles::DISK)->put('exports/report.csv', 'a,b');
        $export = ReportExport::create(['user_id' => $admin->id, 'type' => 'payments', 'status' => 'done', 'file_path' => 'exports/report.csv']);

        $this->actingAs($admin)->get(route('reports.exports.download', $export))->assertOk();
    }
}
