<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\CampusProgramPrice;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Program;
use App\Models\Receipt;
use App\Models\Representative;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use App\Support\ExtracurricularSheet;
use App\Support\FinanceReconcile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExtracurricularSheetPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Student, 2: Enrollment, 3: Campus}
     */
    private function scenario(): array
    {
        Mail::fake();
        Storage::fake('public');

        $campus = Campus::create(['name' => 'Colegio Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);

        $this->actingAs($master)->put(route('campuses.update', $campus), [
            'name' => $campus->name,
            'code' => $campus->code,
            'status' => 'active',
            'logo' => UploadedFile::fake()->image('mater-dei.png', 120, 120),
        ])->assertRedirect(route('campuses.index'));

        $program = Program::create(['name' => 'Inglés', 'code' => 'ING-COL', 'status' => 'active', 'is_extracurricular' => true]);
        CampusProgramPrice::create(['campus_id' => $campus->id, 'program_id' => $program->id, 'amount' => 35, 'currency' => 'USD']);
        $teacher = Teacher::create(['campus_id' => $campus->id, 'first_name' => 'Laura', 'last_name' => 'Quintero', 'status' => 'active']);
        $schedule = ScheduleTemplate::create(['campus_id' => $campus->id, 'days' => ['mon', 'wed'], 'starts_at' => '14:20:00', 'ends_at' => '15:50:00', 'status' => 'active']);
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $campus->id, 'name' => 'Colegio'])->id,
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'schedule_template_id' => $schedule->id,
            'name' => 'Inglés Mater Dei',
            'start_date' => '2026-10-01',
            'end_date' => '2027-07-15',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $campus->id, 'course_id' => $course->id, 'name' => 'ING-MD', 'status' => 'active', 'capacity' => 30]);

        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Valentina',
            'last_name' => 'Rojas',
            'birth_date' => '2017-03-10',
            'school_grade' => '4to grado',
            'school_section' => 'U',
            'emergency_phone' => '0412-1111111',
            'extracurricular_level' => 'Básico',
            'extracurricular_objectives' => 'Fluidez conversacional y expresión oral',
            'teacher_observations' => 'Muy participativa.',
            'payment_condition' => '10 cuotas de 35 USD',
            'status' => 'active',
        ]);
        $representative = Representative::create([
            'campus_id' => $campus->id,
            'first_name' => 'María',
            'last_name' => 'Rojas',
            'document_id' => '12345678',
            'nationality' => 'V',
            'relation' => 'Madre',
            'mobile_phone' => '0414-0000000',
            'email' => 'maria@example.com',
        ]);
        $student->representatives()->attach($representative->id);

        $enrollment = Enrollment::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'enrolled_at' => '2026-10-01',
            'status' => 'active',
            'progress' => 0,
        ]);

        $billing = app(EnrollmentBillingService::class);
        foreach (['2026-10-01', '2026-11-01', '2026-12-01'] as $month) {
            Carbon::setTestNow($month.' 06:05:00');
            $billing->createMonthlyCharge($enrollment, Carbon::parse($month));
        }
        Carbon::setTestNow('2026-12-02 10:00:00');

        $october = $enrollment->charges()->where('billing_period_label', '2026-10')->firstOrFail();
        $payment = Payment::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'amount' => 35,
            'currency' => 'USD',
            'paid_at' => '2026-10-04',
            'method' => 'Zelle',
            'reference' => 'ZL-889',
            'status' => 'confirmed',
        ]);
        PaymentAllocation::create(['payment_id' => $payment->id, 'charge_id' => $october->id, 'amount_applied' => 35]);
        Receipt::create(['campus_id' => $campus->id, 'payment_id' => $payment->id, 'receipt_number' => 'R-00000077', 'issued_at' => '2026-10-04']);
        FinanceReconcile::syncCharge($october);
        $this->artisan('finance:reconcile-charges')->assertSuccessful();

        return [$master, $student->fresh(), $enrollment, $campus->fresh()];
    }

    public function test_monthly_control_reflects_real_charges_and_payments(): void
    {
        [, $student, $enrollment, $campus] = $this->scenario();

        $this->assertNotNull($campus->logo_path);
        $this->assertStringStartsWith('data:image/png;base64,', $campus->logoDataUri());

        $months = ExtracurricularSheet::monthlyControl($enrollment)->keyBy('month');
        $this->assertCount(10, $months);
        $this->assertSame('Octubre', $months['2026-10']['label']);
        $this->assertSame('Pagado', $months['2026-10']['status']);
        $this->assertSame('04/10/2026', $months['2026-10']['paid_at']);
        $this->assertSame('ZL-889 · R-00000077', $months['2026-10']['reference']);
        $this->assertSame('Vencido', $months['2026-11']['status']);
        $this->assertSame('Pendiente', $months['2026-12']['status']);
        $this->assertSame('Por generar', $months['2027-07']['status']);
    }

    public function test_admin_downloads_sheet_pdf_for_extracurricular_student_only(): void
    {
        [$master, $student, , $campus] = $this->scenario();

        $this->actingAs($master)->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Planilla extracurricular (PDF)');

        $response = $this->actingAs($master)->get(route('students.extracurricular-sheet.pdf', $student));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        if ($path = getenv('SHEET_SAMPLE_PATH')) {
            file_put_contents($path, $response->getContent());
        }

        $regular = Student::create(['campus_id' => $campus->id, 'first_name' => 'Pedro', 'last_name' => 'Regular', 'status' => 'active']);
        $this->actingAs($master)->get(route('students.extracurricular-sheet.pdf', $regular))->assertNotFound();
        $this->actingAs($master)->get(route('students.show', $regular))->assertDontSee('Planilla extracurricular (PDF)');
    }
}
