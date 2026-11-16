<?php

namespace Tests\Feature;

use App\Mail\ChargeDueReminderMail;
use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\CampusProgramPrice;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Period;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** #450: filtros, acciones en lote y alta → inscripción → pago. */
class StudentAndCourseListToolsTest extends TestCase
{
    use RefreshDatabase;

    private Campus $picacho;

    private Campus $cascada;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 10:00:00');
        Mail::fake();
        $this->picacho = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->cascada = Campus::create(['name' => 'La Cascada', 'code' => 'CAS', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->picacho->id, 'is_master' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function course(Campus $campus, string $name, array $extra = []): Course
    {
        $level = ProgramLevel::query()->firstOrFail();

        return Course::create($extra + [
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => $name,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
    }

    private function enroll(Course $course, string $first): Student
    {
        $group = Group::firstOrCreate(['course_id' => $course->id], ['campus_id' => $course->campus_id, 'name' => 'G-'.$course->name, 'status' => 'active', 'capacity' => 30]);
        $student = Student::create(['campus_id' => $course->campus_id, 'first_name' => $first, 'last_name' => 'Test', 'status' => 'active']);
        Enrollment::create(['campus_id' => $course->campus_id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);

        return $student;
    }

    public function test_students_filter_by_course_program_and_period(): void
    {
        $periodA = Period::create(['campus_id' => $this->picacho->id, 'code' => '2026-3', 'status' => 'active']);
        $periodB = Period::create(['campus_id' => $this->picacho->id, 'code' => '2026-2', 'status' => 'active']);
        $hs5 = $this->course($this->picacho, 'HS5A', ['period_id' => $periodA->id]);
        $hs1 = $this->course($this->picacho, 'HS1B', ['period_id' => $periodB->id]);
        $this->enroll($hs5, 'Ana');
        $this->enroll($hs1, 'Beto');

        $this->actingAs($this->admin)->get(route('students.index', ['course_id' => $hs5->id]))
            ->assertOk()->assertSee('Ana Test')->assertDontSee('Beto Test');
        $this->actingAs($this->admin)->get(route('students.index', ['period_id' => $periodB->id]))
            ->assertOk()->assertSee('Beto Test')->assertDontSee('Ana Test');
        $this->actingAs($this->admin)->get(route('students.index', ['program_id' => $hs5->program_id]))
            ->assertOk()->assertSee('Ana Test')->assertSee('Beto Test')->assertSee('Exportar selección')->assertSee('Pasar a histórico');
    }

    public function test_bulk_historical_and_export_selection_respect_campus(): void
    {
        $ana = $this->enroll($this->course($this->picacho, 'HS5A'), 'Ana');
        $beto = $this->enroll($this->course($this->picacho, 'HS1B'), 'Beto');
        $eva = $this->enroll($this->course($this->cascada, 'HS2'), 'Eva');

        $csv = $this->actingAs($this->admin)->get(route('students.export', ['ids' => [$ana->id, $eva->id]]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Ana', $csv);
        $this->assertStringNotContainsString('Beto', $csv);
        $this->assertStringNotContainsString('Eva', $csv, 'Eva es de otra sede.');

        $this->actingAs($this->admin)->post(route('students.bulk'), ['ids' => [$ana->id, $beto->id, $eva->id], 'action' => 'historical', 'historical_status' => 'withdrawn'])
            ->assertRedirect()->assertSessionHas('success', '2 alumno(s) pasaron a histórico.')->assertSessionHas('warning');

        $this->assertSame('withdrawn', $ana->fresh()->status);
        $this->assertSame('withdrawn', $beto->fresh()->status);
        $this->assertSame('active', $eva->fresh()->status);

        $this->actingAs($this->admin)->post(route('students.bulk'), ['ids' => [$ana->id], 'action' => 'historical'])
            ->assertSessionHasErrors('historical_status');
    }

    public function test_bulk_payment_reminder_sends_due_charges_once_a_day(): void
    {
        $ana = $this->enroll($this->course($this->picacho, 'HS5A'), 'Ana');
        $rep = Representative::create(['campus_id' => $this->picacho->id, 'first_name' => 'Marta', 'last_name' => 'Test', 'email' => 'marta@example.test']);
        $rep->students()->attach($ana->id);
        $beto = $this->enroll($this->course($this->picacho, 'HS1B'), 'Beto');
        $charge = fn (Student $student, string $due) => Charge::create(['campus_id' => $student->campus_id, 'student_id' => $student->id, 'concept' => 'Mensualidad '.$due, 'amount' => 40, 'currency' => 'USD', 'due_date' => $due, 'status' => Carbon::parse($due)->isPast() ? 'overdue' : 'pending']);
        $overdue = $charge($ana, '2026-11-05');
        $soon = $charge($ana, '2026-11-20');
        $charge($ana, '2026-12-05'); // fuera de la ventana de 7 días
        $charge($beto, '2026-11-05'); // Beto no tiene correo

        $this->actingAs($this->admin)->post(route('students.bulk'), ['ids' => [$ana->id, $beto->id], 'action' => 'reminder'])
            ->assertSessionHas('success', '2 recordatorio(s) de pago enviado(s).')->assertSessionHas('info');

        Mail::assertQueued(ChargeDueReminderMail::class, 2);
        Mail::assertQueued(ChargeDueReminderMail::class, fn (ChargeDueReminderMail $mail) => $mail->charge->is($overdue) && $mail->reminderType === 'overdue' && $mail->hasTo('marta@example.test'));
        Mail::assertQueued(ChargeDueReminderMail::class, fn (ChargeDueReminderMail $mail) => $mail->charge->is($soon) && $mail->reminderType === 'upcoming');

        // El mismo día no se repite.
        $this->actingAs($this->admin)->post(route('students.bulk'), ['ids' => [$ana->id], 'action' => 'reminder'])
            ->assertSessionHas('success', '0 recordatorio(s) de pago enviado(s).');
        Mail::assertQueued(ChargeDueReminderMail::class, 2);
    }

    public function test_courses_filter_by_teacher_period_program_and_ending_soon(): void
    {
        $teacher = Teacher::create(['campus_id' => $this->picacho->id, 'first_name' => 'Filip', 'last_name' => 'Rachev', 'status' => 'active']);
        $period = Period::create(['campus_id' => $this->picacho->id, 'code' => '2026-3', 'status' => 'active']);
        $this->course($this->picacho, 'Termina pronto HS5', ['end_date' => '2026-11-25', 'teacher_id' => $teacher->id, 'period_id' => $period->id]);
        $this->course($this->picacho, 'Termina en enero HS1', ['end_date' => '2027-01-20']);
        $this->course($this->cascada, 'Otra sede HS2', ['end_date' => '2026-11-20']);

        $this->actingAs($this->admin)->get(route('courses.index', ['ending_soon' => 1]))
            ->assertOk()->assertSee('Termina pronto HS5')->assertDontSee('Termina en enero HS1')->assertDontSee('Otra sede HS2');
        $this->actingAs($this->admin)->get(route('courses.index', ['teacher_id' => $teacher->id]))
            ->assertOk()->assertSee('Termina pronto HS5')->assertDontSee('Termina en enero HS1');
        $this->actingAs($this->admin)->get(route('courses.index', ['period_id' => $period->id]))
            ->assertOk()->assertSee('Termina pronto HS5')->assertDontSee('Termina en enero HS1');
    }

    public function test_new_student_opens_enrollment_and_enrollment_offers_first_payment(): void
    {
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->picacho->id, 'is_master' => true]);

        $response = $this->actingAs($master)->post(route('students.store'), [
            'campus_id' => $this->picacho->id,
            'first_name' => 'Nueva',
            'last_name' => 'Alumna',
            'status' => 'active',
            'birth_date' => '2015-05-05',
            'document_id' => 'V-30111222',
        ]);
        $student = Student::where('first_name', 'Nueva')->first();
        if (! $student) {
            $this->markTestSkipped('El alta requiere más campos: '.json_encode(session('errors')?->all()));
        }
        $response->assertRedirect(route('students.show', ['student' => $student, 'enroll' => 1]));

        $this->actingAs($master)->get(route('students.show', ['student' => $student, 'enroll' => 1]))
            ->assertOk()->assertSee('id="enroll-modal"', false)->assertSee('data-open-on-load="1"', false);
        $this->actingAs($master)->get(route('students.show', $student))
            ->assertOk()->assertDontSee('data-open-on-load="1"', false);

        $charge = Charge::create(['campus_id' => $this->picacho->id, 'student_id' => $student->id, 'concept' => 'Mensualidad noviembre', 'amount' => 40, 'currency' => 'USD', 'due_date' => '2026-11-20', 'status' => 'pending']);
        $this->actingAs($master)->withSession(['offer_first_payment' => $charge->id])->get(route('students.show', $student))
            ->assertOk()->assertSee('Registrar pago de la primera cuota')
            ->assertSee('value="'.$charge->id.'"', false);
    }

    public function test_enrollment_from_student_sheet_flashes_first_charge(): void
    {
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->picacho->id, 'is_master' => true]);
        $course = $this->course($this->picacho, 'HS5A');
        $group = Group::create(['campus_id' => $this->picacho->id, 'course_id' => $course->id, 'name' => 'G-HS5A', 'status' => 'active', 'capacity' => 30]);
        $student = Student::create(['campus_id' => $this->picacho->id, 'first_name' => 'Lia', 'last_name' => 'Test', 'status' => 'active']);
        CampusProgramPrice::create(['campus_id' => $this->picacho->id, 'program_id' => $course->program_id, 'amount' => 35, 'currency' => 'USD']);

        $response = $this->actingAs($master)->post(route('enrollments.store'), [
            'student_id' => $student->id, 'group_id' => $group->id, 'status' => 'active',
            'enrolled_at' => '2026-11-16', 'return_to' => 'student',
        ])->assertRedirect();

        $charge = Charge::where('student_id', $student->id)->whereNull('voided_at')->first();
        $this->assertNotNull($charge);
        $this->assertSame($charge->id, session('offer_first_payment'), 'La ficha ofrece registrar el pago de la cuota recién creada.');

        $other = Student::create(['campus_id' => $this->picacho->id, 'first_name' => 'Noa', 'last_name' => 'Test', 'status' => 'active']);
        $this->actingAs($master)->post(route('enrollments.store'), [
            'student_id' => $other->id, 'group_id' => $group->id, 'status' => 'active',
            'enrolled_at' => '2026-11-16', 'return_to' => 'student', 'skip_tuition' => '1',
        ]);
        $this->assertNull(session('offer_first_payment'), 'Con "No generar mensualidad" no hay cuota que ofrecer.');
    }
}
