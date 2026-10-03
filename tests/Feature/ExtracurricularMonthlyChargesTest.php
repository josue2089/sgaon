<?php

namespace Tests\Feature;

use App\Mail\ChargePendingMail;
use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\CampusProgramPrice;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Program;
use App\Models\Student;
use App\Services\EnrollmentBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExtracurricularMonthlyChargesTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->campus = Campus::create(['name' => 'Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $program = Program::create(['name' => 'Danza', 'code' => 'DANZA', 'status' => 'active', 'is_extracurricular' => true]);
        CampusProgramPrice::create(['campus_id' => $this->campus->id, 'program_id' => $program->id, 'amount' => 35, 'currency' => 'USD']);

        $this->course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'Nivel'])->id,
            'program_id' => $program->id,
            'name' => 'Danza 2026-2027',
            'start_date' => '2026-10-01',
            'end_date' => '2027-07-15',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enroll(string $enrolledAt, string $status = 'active'): Enrollment
    {
        $group = Group::firstOrCreate(
            ['course_id' => $this->course->id],
            ['campus_id' => $this->campus->id, 'name' => 'DANZA-G1', 'status' => 'active', 'capacity' => 30],
        );
        $student = Student::create([
            'campus_id' => $this->campus->id,
            'first_name' => 'Alumno',
            'last_name' => $enrolledAt,
            'email' => 'alumno-'.$enrolledAt.'@student.test',
            'status' => 'active',
        ]);

        return Enrollment::create([
            'campus_id' => $this->campus->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'enrolled_at' => $enrolledAt,
            'status' => $status,
            'progress' => 0,
        ]);
    }

    private function runMonths(string $from, int $count): void
    {
        $month = Carbon::parse($from)->startOfMonth();
        for ($i = 0; $i < $count; $i++) {
            Carbon::setTestNow($month->copy()->day(1)->setTime(6, 5));
            $this->artisan('finance:generate-extracurricular-charges')->assertSuccessful();
            $month->addMonth();
        }
    }

    private function charges(Enrollment $enrollment)
    {
        return Charge::query()->where('enrollment_id', $enrollment->id)->orderBy('billing_period_label')->get();
    }

    public function test_october_enrollment_gets_ten_monthly_charges_due_on_the_fifth(): void
    {
        $enrollment = $this->enroll('2026-10-01');

        $this->runMonths('2026-10-01', 12);
        $this->runMonths('2026-10-01', 1);

        $charges = $this->charges($enrollment);
        $this->assertCount(10, $charges);
        $this->assertSame('2026-10', $charges->first()->billing_period_label);
        $this->assertSame('2027-07', $charges->last()->billing_period_label);
        $this->assertTrue($charges->every(fn (Charge $c) => $c->amount === 35.0 && $c->currency === 'USD' && $c->due_date->day === 5));
        $this->assertSame('Mensualidad Danza — Octubre 2026', $charges->first()->concept);
        $this->assertSame('monthly_auto', $charges->first()->origin);
        Mail::assertSent(ChargePendingMail::class, 10);
    }

    public function test_mid_year_enrollment_charges_from_enrollment_month(): void
    {
        Carbon::setTestNow('2026-11-20 10:00:00');
        $enrollment = $this->enroll('2026-11-20');

        $first = app(EnrollmentBillingService::class)->createTuitionCharge($enrollment);
        $this->assertSame('2026-11', $first->billing_period_label);
        $this->assertSame('2026-11-20', $first->due_date->toDateString(), 'Si se inscribe después del día 5, vence el día de la inscripción.');

        $this->runMonths('2026-11-01', 10);

        $this->assertCount(9, $this->charges($enrollment));
    }

    public function test_unpaid_charge_becomes_overdue_after_the_fifth(): void
    {
        $enrollment = $this->enroll('2026-10-01');
        $this->runMonths('2026-10-01', 1);

        Carbon::setTestNow('2026-10-06 06:00:00');
        $this->artisan('finance:reconcile-charges')->assertSuccessful();

        $this->assertSame('overdue', $this->charges($enrollment)->first()->status);
    }

    public function test_withdrawn_enrollment_and_regular_courses_get_no_monthly_charges(): void
    {
        $withdrawn = $this->enroll('2026-10-01', 'withdrawn');

        $regularProgram = Program::create(['name' => 'Inglés regular', 'code' => 'REG', 'status' => 'active', 'is_extracurricular' => false]);
        CampusProgramPrice::create(['campus_id' => $this->campus->id, 'program_id' => $regularProgram->id, 'amount' => 80, 'currency' => 'USD']);
        $regularCourse = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => $this->course->academic_level_id,
            'program_id' => $regularProgram->id,
            'name' => 'Regular',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-15',
            'status' => 'active',
        ]);
        $regularGroup = Group::create(['campus_id' => $this->campus->id, 'course_id' => $regularCourse->id, 'name' => 'REG-G1', 'status' => 'active', 'capacity' => 30]);
        $regular = Enrollment::create([
            'campus_id' => $this->campus->id,
            'student_id' => $withdrawn->student_id,
            'group_id' => $regularGroup->id,
            'enrolled_at' => '2026-10-01',
            'status' => 'active',
            'progress' => 0,
        ]);

        $this->runMonths('2026-10-01', 3);

        $this->assertCount(0, $this->charges($withdrawn));
        $this->assertCount(0, $this->charges($regular), 'Los cursos regulares no generan cuotas mensuales.');

        // Al inscribir en un curso regular sigue creándose un único cargo.
        Carbon::setTestNow('2026-10-01 10:00:00');
        $single = app(EnrollmentBillingService::class)->createTuitionCharge($regular);
        $this->assertSame(80.0, $single->amount);
        $this->assertSame('enrollment_auto', $single->origin);
    }

    public function test_dry_run_counts_without_creating(): void
    {
        $this->enroll('2026-10-01');
        Carbon::setTestNow('2026-10-01 06:05:00');

        $this->artisan('finance:generate-extracurricular-charges', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Cuotas extracurriculares 2026-10: 1 nuevas de 1')
            ->assertSuccessful();

        $this->assertSame(0, Charge::query()->count());
    }
}
