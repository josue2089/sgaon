<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\CampusProgramPrice;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CampusProgramPriceTest extends TestCase
{
    use RefreshDatabase;

    private function master(Campus $campus): User
    {
        return User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
    }

    private function enroll(Campus $campus, ProgramLevel $level, string $suffix): Enrollment
    {
        $academicLevel = AcademicLevel::create(['campus_id' => $campus->id, 'name' => 'Nivel '.$suffix]);
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => 'Curso '.$suffix,
            'code' => 'C-'.$suffix,
            'start_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);
        $group = Group::create([
            'campus_id' => $campus->id,
            'course_id' => $course->id,
            'name' => 'G-'.$suffix,
            'period' => '2026-Q4',
            'status' => 'active',
        ]);
        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Alumno',
            'last_name' => $suffix,
            'email' => 'alumno'.$suffix.'@student.test',
            'status' => 'active',
        ]);

        return Enrollment::create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'progress' => 0,
        ]);
    }

    public function test_master_can_save_replace_and_delete_campus_price(): void
    {
        $campus = Campus::create(['name' => 'Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $program = Program::create(['name' => 'Danza', 'code' => 'DANZA', 'status' => 'active', 'is_extracurricular' => true]);
        $master = $this->master($campus);

        $this->actingAs($master)
            ->post(route('campuses.program-prices.store', $campus), ['program_id' => $program->id, 'amount' => 30, 'currency' => 'USD'])
            ->assertRedirect(route('campuses.show', $campus));
        $this->actingAs($master)
            ->post(route('campuses.program-prices.store', $campus), ['program_id' => $program->id, 'amount' => 35, 'currency' => 'USD']);

        $prices = CampusProgramPrice::query()->where('campus_id', $campus->id)->get();
        $this->assertCount(1, $prices);
        $this->assertSame(35.0, $prices->first()->amount);
        $this->assertSame('USD', $prices->first()->currency);
        $this->assertTrue(AuditLog::query()->where('action', 'campus.program_price.save')->exists());

        $this->actingAs($master)->get(route('campuses.show', $campus))
            ->assertOk()
            ->assertSee('Precios de mensualidad por programa')
            ->assertSee('Danza');

        $this->actingAs($master)
            ->delete(route('campuses.program-prices.destroy', [$campus, $prices->first()]))
            ->assertRedirect(route('campuses.show', $campus));
        $this->assertFalse(CampusProgramPrice::query()->exists());
    }

    public function test_non_master_admin_cannot_manage_prices(): void
    {
        $campus = Campus::create(['name' => 'Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $program = Program::query()->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => false]);

        $this->actingAs($admin)
            ->post(route('campuses.program-prices.store', $campus), ['program_id' => $program->id, 'amount' => 35, 'currency' => 'USD'])
            ->assertForbidden();

        $this->assertFalse(CampusProgramPrice::query()->exists());
    }

    public function test_enrollment_charge_uses_campus_price_and_falls_back_to_eur(): void
    {
        Mail::fake();
        $level = ProgramLevel::query()->where('code', 'HS2B')->firstOrFail();
        $level->update(['base_price_eur' => 240]);

        $school = Campus::create(['name' => 'Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $academy = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        CampusProgramPrice::create(['campus_id' => $school->id, 'program_id' => $level->program_id, 'amount' => 35, 'currency' => 'USD']);

        $schoolCharge = app(EnrollmentBillingService::class)->createTuitionCharge($this->enroll($school, $level, 'A'));
        $academyCharge = app(EnrollmentBillingService::class)->createTuitionCharge($this->enroll($academy, $level, 'B'));

        $this->assertSame(35.0, $schoolCharge->amount);
        $this->assertSame('USD', $schoolCharge->currency);
        $this->assertSame(240.0, $academyCharge->amount);
        $this->assertSame('EUR', $academyCharge->currency);
    }

    public function test_campus_price_creates_charge_for_program_without_level_price(): void
    {
        Mail::fake();
        $school = Campus::create(['name' => 'Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $level = ProgramLevel::query()
            ->whereNull('base_price_eur')
            ->whereHas('program', fn ($query) => $query->whereNull('base_price_eur'))
            ->firstOrFail();
        $program = $level->program;

        $enrollment = $this->enroll($school, $level, 'C');
        $this->assertNull(app(EnrollmentBillingService::class)->createTuitionCharge($enrollment));

        CampusProgramPrice::create(['campus_id' => $school->id, 'program_id' => $program->id, 'amount' => 35, 'currency' => 'USD']);
        $charge = app(EnrollmentBillingService::class)->createTuitionCharge($enrollment);

        $this->assertNotNull($charge);
        $this->assertSame(35.0, $charge->amount);
        $this->assertSame('USD', $charge->currency);
    }

    public function test_bolivares_payment_converts_against_usd_charge(): void
    {
        $charge = new Charge(['amount' => 35, 'currency' => 'USD']);

        $converted = PaymentCurrencyConverter::resolveForCharge('VES', 7000, $charge, 200.0);

        $this->assertSame(35.0, $converted['amount']);
        $this->assertSame(PaymentCurrencyConverter::CURRENCY_VES, $converted['currency']);
    }
}
