<?php

namespace Tests\Feature;

use App\Mail\DailyPendingMail;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DailyPendingSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $picachoAdmin;

    private User $cascadaAdmin;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-10-21 11:00:00');

        $picacho = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $cascada = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $this->picachoAdmin = User::factory()->create(['role' => 'admin', 'campus_id' => $picacho->id, 'is_master' => false, 'email' => 'picacho@test.com']);
        $this->cascadaAdmin = User::factory()->create(['role' => 'admin', 'campus_id' => $cascada->id, 'is_master' => false, 'email' => 'cascada@test.com']);
        $this->master = User::factory()->create(['role' => 'admin', 'campus_id' => $picacho->id, 'is_master' => true, 'email' => 'master@test.com']);
        User::factory()->create(['role' => 'admin', 'campus_id' => $picacho->id, 'status' => 'inactive', 'email' => 'off@test.com']);
        User::factory()->create(['role' => 'teacher', 'campus_id' => $picacho->id, 'email' => 'teacher@test.com']);

        // Pendientes solo en Picacho: un alumno activo sin inscripción y un cargo vencido hace más de 30 días.
        $student = Student::create(['campus_id' => $picacho->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'status' => 'active']);
        Charge::create(['campus_id' => $picacho->id, 'student_id' => $student->id, 'concept' => 'Mensualidad agosto', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => '2026-08-05', 'status' => 'overdue']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sends_each_admin_only_their_campus_and_skips_empty_days(): void
    {
        $this->artisan('admins:send-daily-pending')
            ->expectsOutputToContain('Correos: 2. Sin pendientes (no se envía): 1.')
            ->assertSuccessful();

        Mail::assertSent(DailyPendingMail::class, 2);
        Mail::assertSent(DailyPendingMail::class, fn (DailyPendingMail $mail) => $mail->hasTo('picacho@test.com') && $mail->scopeLabel === 'Picacho');
        Mail::assertSent(DailyPendingMail::class, fn (DailyPendingMail $mail) => $mail->hasTo('master@test.com') && $mail->scopeLabel === 'Todas las sedes');
        Mail::assertNotSent(DailyPendingMail::class, fn (DailyPendingMail $mail) => $mail->hasTo('cascada@test.com') || $mail->hasTo('off@test.com') || $mail->hasTo('teacher@test.com'));
    }

    public function test_mail_lists_only_pending_items_with_direct_links(): void
    {
        $this->artisan('admins:send-daily-pending')->assertSuccessful();

        Mail::assertSent(DailyPendingMail::class, function (DailyPendingMail $mail) {
            if (! $mail->hasTo('picacho@test.com')) {
                return false;
            }
            $keys = array_column($mail->items, 'key');
            $html = $mail->render();

            return $keys === ['overdue_30', 'no_enrollment']
                && str_contains($html, 'Alumnos activos sin inscripción')
                && str_contains($html, route('students.index', ['enrollment' => 'none']))
                && $mail->envelope()->subject === 'Pendientes de hoy en ON English (2)';
        });
    }

    public function test_dry_run_sends_nothing_and_is_scheduled_at_seven_caracas(): void
    {
        $this->artisan('admins:send-daily-pending', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] picacho@test.com (Picacho)')
            ->assertSuccessful();
        Mail::assertNothingSent();

        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'admins:send-daily-pending'));
        $this->assertNotNull($event);
        $this->assertSame('0 7 * * *', $event->expression);
        $this->assertSame('America/Caracas', $event->timezone);
    }
}
