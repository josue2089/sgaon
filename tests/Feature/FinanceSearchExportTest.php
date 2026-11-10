<?php

namespace Tests\Feature;

use App\Mail\PaymentReceiptMail;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use App\Support\WhatsappLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FinanceSearchExportTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Campus $other;

    private User $master;

    private Student $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->other = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $this->master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->ana = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'document_id' => 'V-30111222', 'status' => 'active']);
        Representative::create(['campus_id' => $this->campus->id, 'first_name' => 'Marta', 'last_name' => 'Pérez', 'document_id' => 'V-11222333', 'mobile_phone' => '0414-1234567', 'email' => 'marta@test.com'])
            ->students()->attach($this->ana->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function charge(Student $student, string $concept, string $due, string $status = 'overdue'): Charge
    {
        return Charge::create(['campus_id' => $student->campus_id, 'student_id' => $student->id, 'concept' => $concept, 'charge_type' => 'tuition', 'amount' => 40.5, 'currency' => 'USD', 'due_date' => $due, 'status' => $status]);
    }

    private function payment(Student $student, string $paidAt, string $reference, string $currency = 'USD', float $original = 40): Payment
    {
        $payment = Payment::create(['campus_id' => $student->campus_id, 'student_id' => $student->id, 'amount' => 40, 'original_amount' => $original, 'currency' => $currency, 'paid_at' => $paidAt, 'method' => 'Zelle', 'reference' => $reference, 'status' => 'confirmed']);
        Receipt::create(['campus_id' => $student->campus_id, 'payment_id' => $payment->id, 'receipt_number' => 'R-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT), 'issued_at' => $paidAt]);

        return $payment;
    }

    public function test_search_by_concept_student_document_reference_and_receipt(): void
    {
        $this->charge($this->ana, 'Mensualidad agosto', '2026-08-05');
        $luis = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Luis', 'last_name' => 'Gil', 'status' => 'active']);
        $this->charge($luis, 'Materiales', '2026-09-05');

        $this->actingAs($this->master)->get(route('finance.index', ['q' => 'agosto']))->assertSee('Mensualidad agosto')->assertDontSee('Materiales');
        $this->actingAs($this->master)->get(route('finance.index', ['q' => '30111222']))->assertSee('Mensualidad agosto')->assertDontSee('Materiales');
        $this->actingAs($this->master)->get(route('finance.index', ['q' => 'Luis Gil']))->assertSee('Materiales')->assertDontSee('Mensualidad agosto');

        $this->payment($this->ana, '2026-10-10', 'ZL-77');
        $other = $this->payment($luis, '2026-10-11', 'PM-99');
        $this->actingAs($this->master)->get(route('finance.index', ['tab' => 'pagos', 'q' => 'ZL-77']))->assertSee('ZL-77')->assertDontSee('PM-99');
        $this->actingAs($this->master)->get(route('finance.index', ['tab' => 'pagos', 'q' => $other->receipt->receipt_number]))->assertSee('Luis Gil')->assertDontSee('Ana Pérez');
    }

    public function test_mora_csv_is_in_spanish_with_bom_representative_and_labels(): void
    {
        $this->charge($this->ana, 'Mensualidad agosto', '2026-08-05');

        $csv = $this->actingAs($this->master)->get(route('finance.index', ['export' => 'mora_csv']))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Alumno;"Cédula del alumno";Representante;"Cédula del representante";"Teléfono del representante"', $csv);
        $this->assertStringContainsString('V-11222333', $csv);
        $this->assertStringContainsString('0414-1234567', $csv);
        $this->assertStringContainsString('40,50', $csv);
        $this->assertStringContainsString('05/08/2026', $csv);
        $this->assertStringContainsString('Vencido', $csv);
        $this->assertStringNotContainsString(';overdue', $csv);
    }

    public function test_payments_report_filters_by_range_and_campus_with_totals_by_currency(): void
    {
        $this->payment($this->ana, '2026-10-01', 'IN-RANGE-1');
        $this->payment($this->ana, '2026-10-05', 'IN-RANGE-VES', 'VES', 12000);
        $this->payment($this->ana, '2026-09-01', 'OUT-OF-RANGE');
        $carla = Student::create(['campus_id' => $this->other->id, 'first_name' => 'Carla', 'last_name' => 'Ruiz', 'status' => 'active']);
        $this->payment($carla, '2026-10-02', 'OTHER-CAMPUS');

        $response = $this->actingAs($this->master)->get(route('reports.payments', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'campus_id' => $this->campus->id]))
            ->assertOk()
            ->assertSee('01/10/2026')
            ->assertDontSee('01/09/2026')
            ->assertDontSee('Carla Ruiz');
        $totals = $response->viewData('paymentTotals')->keyBy('currency_code');
        $this->assertSame(1, (int) $totals['USD']->total_count);
        $this->assertEquals(12000, (float) $totals['VES']->original_total);

        $csv = $this->actingAs($this->master)->get(route('reports.payments', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'campus_id' => $this->campus->id, 'export' => 'payments_detail_csv']))->streamedContent();
        $this->assertStringContainsString('IN-RANGE-1', $csv);
        $this->assertStringContainsString('"Fecha de pago";Recibo;Alumno', $csv);
        $this->assertStringNotContainsString('OUT-OF-RANGE', $csv);
        $this->assertStringNotContainsString('OTHER-CAMPUS', $csv);
    }

    public function test_receipt_can_be_resent_and_shared_by_whatsapp(): void
    {
        Mail::fake();
        $payment = $this->payment($this->ana, '2026-10-10', 'ZL-77');

        $this->actingAs($this->master)->get(route('finance.receipts.show', $payment->receipt))
            ->assertOk()
            ->assertSee('Reenviar por correo')
            ->assertSee('https://wa.me/584141234567?text=', false);

        $this->actingAs($this->master)->post(route('finance.receipts.resend', $payment->receipt))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'marta@test.com'));
        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->hasTo('marta@test.com'));
        $this->assertTrue(AuditLog::query()->where('action', 'receipt.resent')->exists());

        $otherAdmin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->other->id, 'is_master' => false]);
        $this->actingAs($otherAdmin)->post(route('finance.receipts.resend', $payment->receipt))->assertForbidden();
    }

    public function test_queued_payments_export_is_in_spanish(): void
    {
        \Illuminate\Support\Facades\Storage::fake(\App\Support\PrivateFiles::DISK);
        $this->charge($this->ana, 'Mensualidad agosto', '2026-08-05');
        $this->payment($this->ana, '2026-10-10', 'ZL-77');
        $export = \App\Models\ReportExport::create(['user_id' => $this->master->id, 'type' => 'payments', 'status' => 'pending', 'filters' => []]);

        (new \App\Jobs\GenerateReportExportJob($export->id))->handle();

        $csv = \Illuminate\Support\Facades\Storage::disk(\App\Support\PrivateFiles::DISK)->get($export->fresh()->file_path);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Alumno;Cédula;Concepto', $csv);
        $this->assertStringContainsString('40,50', $csv);
        $this->assertStringContainsString('Vencido', $csv);
        $this->assertStringContainsString('"Fecha de pago"', $csv);
    }

    public function test_whatsapp_phone_normalization(): void
    {
        $this->assertSame('584141234567', WhatsappLink::normalizePhone('0414-123.45.67'));
        $this->assertSame('584241234567', WhatsappLink::normalizePhone('+58 424 1234567'));
        $this->assertSame('584121234567', WhatsappLink::normalizePhone('4121234567'));
        $this->assertNull(WhatsappLink::normalizePhone('123'));
    }
}
