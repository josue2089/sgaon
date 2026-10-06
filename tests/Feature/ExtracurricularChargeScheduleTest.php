<?php

namespace Tests\Feature;

use App\Mail\ChargePendingMail;
use App\Mail\ChargeScheduleMail;
use App\Models\AcademicLevel;
use App\Models\Alert;
use App\Models\Campus;
use App\Models\CampusProgramPrice;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use App\Support\FinanceReconcile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExtracurricularChargeScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Course $course;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-10-06 10:00:00');

        $this->campus = Campus::create(['name' => 'Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $program = Program::create(['name' => 'Robótica', 'code' => 'ROB-COL', 'status' => 'active', 'is_extracurricular' => true]);
        CampusProgramPrice::create(['campus_id' => $this->campus->id, 'program_id' => $program->id, 'amount' => 35, 'currency' => 'USD']);
        $this->course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'Colegio'])->id,
            'program_id' => $program->id,
            'name' => 'Robótica Mater Dei',
            'start_date' => '2026-10-01',
            'end_date' => '2027-07-15',
            'status' => 'active',
        ]);
        $this->group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $this->course->id, 'name' => 'ROB-MD', 'status' => 'active', 'capacity' => 30]);
        $this->course->update(['managed_group_id' => $this->group->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enroll(string $name, string $enrolledAt = '2026-10-01'): Enrollment
    {
        $student = Student::create([
            'campus_id' => $this->campus->id,
            'first_name' => $name,
            'last_name' => 'Pérez',
            'email' => strtolower($name).'@student.test',
            'status' => 'active',
        ]);

        return Enrollment::create([
            'campus_id' => $this->campus->id,
            'student_id' => $student->id,
            'group_id' => $this->group->id,
            'enrolled_at' => $enrolledAt,
            'status' => 'active',
            'progress' => 0,
        ]);
    }

    private function activeCharges(Enrollment $enrollment)
    {
        return Charge::query()->where('enrollment_id', $enrollment->id)->whereNull('voided_at')->orderBy('due_date')->get();
    }

    public function test_enrolling_creates_all_ten_charges_with_a_single_schedule_email(): void
    {
        Carbon::setTestNow('2026-10-03 10:00:00');
        $enrollment = $this->enroll('Ana');

        app(EnrollmentBillingService::class)->createTuitionCharge($enrollment);

        $charges = $this->activeCharges($enrollment);
        $this->assertCount(10, $charges);
        $this->assertSame('2026-10-05', $charges->first()->due_date->toDateString());
        $this->assertSame('2027-07-05', $charges->last()->due_date->toDateString());
        $this->assertTrue($charges->every(fn (Charge $c) => $c->amount === 35.0 && $c->due_date->day === 5));

        Mail::assertSent(ChargeScheduleMail::class, 1);
        Mail::assertSent(ChargeScheduleMail::class, fn (ChargeScheduleMail $mail) => $mail->charges->count() === 10);
        Mail::assertNotSent(ChargePendingMail::class);

        $html = (new ChargeScheduleMail($enrollment->fresh(['student', 'group.course.program']), $charges))->render();
        $this->assertStringContainsString('Robótica', $html);
        $this->assertStringContainsString('Mensualidad Robótica — Octubre 2026', $html);
        $this->assertStringContainsString('$350,00', $html);

        // Las cuotas futuras no generan alerta de mora.
        $this->assertFalse(Alert::query()->where('student_id', $enrollment->student_id)->where('type', 'finance')->where('status', 'open')->exists());

        // El cron diario no duplica.
        $this->artisan('finance:generate-extracurricular-charges')->assertSuccessful();
        $this->assertCount(10, $this->activeCharges($enrollment));
    }

    public function test_course_button_generates_missing_charges_without_duplicating(): void
    {
        $existing = $this->enroll('Luis');
        app(EnrollmentBillingService::class)->createMonthlyCharge($existing, Carbon::parse('2026-10-01'));
        $this->assertCount(1, $this->activeCharges($existing));

        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->actingAs($admin)->get(route('courses.show', $this->course))
            ->assertOk()
            ->assertSee('Generar cuotas faltantes');

        $this->actingAs($admin)
            ->post(route('courses.extracurricular-charges.generate', $this->course))
            ->assertRedirect(route('courses.show', $this->course))
            ->assertSessionHas('success', 'Se generaron 9 cuota(s) faltante(s) para 1 alumno(s).');
        $this->assertCount(10, $this->activeCharges($existing));

        $this->actingAs($admin)
            ->post(route('courses.extracurricular-charges.generate', $this->course))
            ->assertSessionHas('success', 'Todas las inscripciones activas ya tienen sus cuotas generadas.');
        $this->assertCount(10, $this->activeCharges($existing));
    }

    public function test_withdrawing_voids_only_future_unpaid_charges(): void
    {
        $enrollment = $this->enroll('Sofía');
        app(EnrollmentBillingService::class)->createTuitionCharge($enrollment);

        // Febrero pagado por adelantado.
        $february = $enrollment->charges()->where('billing_period_label', '2027-02')->firstOrFail();
        $payment = Payment::create(['campus_id' => $this->campus->id, 'student_id' => $enrollment->student_id, 'amount' => 35, 'currency' => 'USD', 'paid_at' => '2026-10-06', 'method' => 'Zelle', 'reference' => 'ZL-1', 'status' => 'confirmed']);
        PaymentAllocation::create(['payment_id' => $payment->id, 'charge_id' => $february->id, 'amount_applied' => 35]);
        FinanceReconcile::syncCharge($february);

        Carbon::setTestNow('2026-12-10 10:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->actingAs($admin)
            ->delete(route('courses.students.remove', [$this->course, $enrollment]))
            ->assertRedirect(route('courses.show', $this->course));

        $this->assertSame('withdrawn', $enrollment->fresh()->status);
        $remaining = $this->activeCharges($enrollment)->pluck('billing_period_label')->all();
        $this->assertSame(['2026-10', '2026-11', '2026-12', '2027-02'], $remaining, 'Se mantienen las vencidas y la pagada; se anulan las futuras sin pagos.');

        $voided = Charge::query()->where('enrollment_id', $enrollment->id)->whereNotNull('voided_at')->get();
        $this->assertCount(6, $voided);
        $this->assertTrue($voided->every(fn (Charge $c) => $c->void_reason === 'Inscripción retirada'));
    }
}
