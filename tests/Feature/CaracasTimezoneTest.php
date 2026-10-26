<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\FinanceReconcile;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CaracasTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // 21/10 a las 21:30 en Caracas: en UTC ya es 22/10.
        Carbon::setTestNow(Carbon::parse('2026-10-22 01:30:00', 'UTC')->setTimezone(config('app.timezone')));
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_is_the_caracas_day_at_night(): void
    {
        $this->assertSame('America/Caracas', config('app.timezone'));
        $this->assertSame('2026-10-21', today()->toDateString());

        $user = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]);
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Filip', 'last_name' => 'Rachev', 'user_id' => $user->id, 'status' => 'active']);
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher->id,
            'name' => 'HS4A Nocturno',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id, 'name' => 'HS4A-N', 'status' => 'active', 'capacity' => 20]);
        Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'group_id' => $group->id, 'status' => 'active', 'progress' => 0]);
        ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-10-21', 'starts_at' => '19:00:00', 'ends_at' => '21:00:00']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('HS4A Nocturno');
    }

    public function test_charge_is_not_overdue_until_its_day_ends(): void
    {
        $dueToday = Charge::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'concept' => 'Vence hoy', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => '2026-10-21', 'status' => 'pending']);
        $dueYesterday = Charge::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'concept' => 'Venció ayer', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => '2026-10-20', 'status' => 'pending']);

        $this->assertSame('pending', FinanceReconcile::syncCharge($dueToday)->status);
        $this->assertSame('overdue', FinanceReconcile::syncCharge($dueYesterday)->status);
    }

    public function test_scheduled_tasks_run_on_caracas_time(): void
    {
        $events = collect(app(Schedule::class)->events());
        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertSame('America/Caracas', (string) $event->timezone, $event->command);
        }
    }

    public function test_timezone_does_not_depend_on_env(): void
    {
        $previous = getenv('APP_TIMEZONE');
        putenv('APP_TIMEZONE=UTC');
        $_ENV['APP_TIMEZONE'] = $_SERVER['APP_TIMEZONE'] = 'UTC';
        try {
            $config = require config_path('app.php');
        } finally {
            $previous === false ? putenv('APP_TIMEZONE') : putenv("APP_TIMEZONE={$previous}");
            unset($_ENV['APP_TIMEZONE'], $_SERVER['APP_TIMEZONE']);
        }

        $this->assertSame('America/Caracas', $config['timezone']);
        $this->assertSame('America/Caracas', $config['schedule_timezone']);

        $reminder = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'finance:send-payment-reminders'));
        $this->assertSame('0 7 * * *', $reminder->expression);
        $this->assertSame('America/Caracas', (string) $reminder->timezone);
    }

    public function test_shifts_old_utc_datetimes_once_and_keeps_local_ones(): void
    {
        $old = Payment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'amount' => 40, 'currency' => 'USD', 'paid_at' => '2026-10-10', 'status' => 'confirmed']);
        $new = Payment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'amount' => 40, 'currency' => 'USD', 'paid_at' => '2026-10-21', 'status' => 'confirmed']);
        $charge = Charge::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'concept' => 'Anulado', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => '2026-10-05', 'status' => 'pending']);
        // Antes del #368: el DATETIME guardó la hora UTC (4 h por delante del TIMESTAMP, que se lee en Caracas).
        DB::table('payments')->where('id', $old->id)->update(['created_at' => '2026-10-10 14:00:00', 'paid_at_datetime' => '2026-10-10 18:00:00']);
        DB::table('payments')->where('id', $new->id)->update(['created_at' => '2026-10-21 20:00:00', 'paid_at_datetime' => '2026-10-21 20:00:00']);
        DB::table('charges')->where('id', $charge->id)->update(['updated_at' => '2026-10-05 21:00:00', 'voided_at' => '2026-10-06 01:00:00']);

        $this->artisan('data:shift-utc-datetimes', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] No se guardó nada.')
            ->assertSuccessful();
        $this->assertSame('2026-10-10 18:00:00', DB::table('payments')->where('id', $old->id)->value('paid_at_datetime'));

        $this->artisan('data:shift-utc-datetimes')
            ->expectsOutputToContain('Fechas corregidas a hora de Caracas.')
            ->assertSuccessful();
        $this->assertSame('2026-10-10 14:00:00', DB::table('payments')->where('id', $old->id)->value('paid_at_datetime'));
        $this->assertSame('2026-10-21 20:00:00', DB::table('payments')->where('id', $new->id)->value('paid_at_datetime'));
        $this->assertSame('2026-10-05 21:00:00', DB::table('charges')->where('id', $charge->id)->value('voided_at'));
        $this->assertTrue(AuditLog::query()->where('action', 'data.utc_datetimes_shifted')->exists());

        $this->artisan('data:shift-utc-datetimes')
            ->expectsOutputToContain('Ya se aplicó antes')
            ->assertSuccessful();
        $this->assertSame('2026-10-10 14:00:00', DB::table('payments')->where('id', $old->id)->value('paid_at_datetime'));
    }
}
