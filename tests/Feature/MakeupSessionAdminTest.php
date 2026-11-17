<?php

namespace Tests\Feature;

use App\Mail\MakeupSessionChangedMail;
use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** #452: bloques recuperativos (próximos, crear, editar, cancelar) y selector de alumnos remoto. */
class MakeupSessionAdminTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private Teacher $teacher;

    private ProgramLevel $level;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-11-17 09:00:00');
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Sofia', 'last_name' => 'Baron', 'status' => 'active']);
        $this->level = ProgramLevel::query()->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function block(string $date, array $extra = []): MakeupSession
    {
        return MakeupSession::create($extra + [
            'campus_id' => $this->campus->id, 'teacher_id' => $this->teacher->id,
            'program_id' => $this->level->program_id, 'program_level_id' => $this->level->id,
            'session_date' => $date, 'starts_at' => '10:00:00', 'ends_at' => '11:30:00', 'capacity' => 5, 'status' => 'open',
        ]);
    }

    /** Alumno con representante, una falta y la recuperativa reservada en el bloque. */
    private function bookStudent(MakeupSession $block, string $name = 'Ana'): MakeupRequest
    {
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $this->level->program_id, 'program_level_id' => $this->level->id,
            'name' => 'HS5A '.$name, 'start_date' => '2026-09-01', 'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'name' => 'G-'.$name, 'status' => 'active', 'capacity' => 30]);
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => $name, 'last_name' => 'Test', 'status' => 'active']);
        Representative::create(['campus_id' => $this->campus->id, 'first_name' => 'Rep', 'last_name' => $name, 'email' => strtolower($name).'.rep@example.test'])->students()->attach($student->id);
        $enrollment = Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $missed = ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => '2026-11-10', 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);
        $request = MakeupRecoveryEngine::syncForAttendanceRecord(AttendanceRecord::create(['class_session_id' => $missed->id, 'enrollment_id' => $enrollment->id, 'status' => 'absent']));
        $request->forceFill(['status' => MakeupRequest::STATUS_APPROVED_FOR_BOOKING])->save();
        MakeupRecoveryEngine::book($request->fresh(), $block);

        return $request->fresh();
    }

    public function test_index_shows_upcoming_blocks_by_default_and_past_on_demand(): void
    {
        $this->block('2026-11-20', ['notes' => 'x']);
        $this->block('2026-11-03', ['starts_at' => '15:00:00', 'ends_at' => '16:00:00']);
        Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Zoe', 'last_name' => 'Nolista', 'status' => 'active']);

        $this->actingAs($this->admin)->get(route('makeups.index'))
            ->assertOk()->assertSee('10:00 - 11:30')->assertDontSee('15:00 - 16:00')
            ->assertSee('Nuevo bloque')->assertSee('data-remote-url', false)
            ->assertDontSee('Zoe Nolista');

        $this->actingAs($this->admin)->get(route('makeups.index', ['sessions' => 'past']))
            ->assertOk()->assertSee('15:00 - 16:00')->assertDontSee('10:00 - 11:30');
    }

    public function test_create_block_requires_level_of_the_program(): void
    {
        $otherProgram = Program::create(['name' => 'Ajedrez', 'code' => 'AJD', 'status' => 'active']);
        $payload = ['teacher_id' => $this->teacher->id, 'program_id' => $otherProgram->id, 'program_level_id' => $this->level->id,
            'session_date' => '2026-11-25', 'starts_at' => '10:00', 'ends_at' => '11:00', 'capacity' => 6, 'status' => 'open', 'form' => 'create'];

        $this->actingAs($this->admin)->post(route('makeups.sessions.store'), $payload)->assertSessionHasErrors('program_level_id');
        $this->actingAs($this->admin)->post(route('makeups.sessions.store'), ['program_id' => $this->level->program_id] + $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, MakeupSession::count());
    }

    public function test_edit_block_respects_bookings_and_notifies_on_reschedule(): void
    {
        $block = $this->block('2026-11-20');
        $request = $this->bookStudent($block);
        $this->bookStudent($block, 'Beto');
        $payload = ['teacher_id' => $this->teacher->id, 'program_id' => $this->level->program_id, 'program_level_id' => $this->level->id,
            'session_date' => '2026-11-20', 'starts_at' => '10:00', 'ends_at' => '11:30', 'capacity' => 1];

        $this->actingAs($this->admin)->put(route('makeups.sessions.update', $block), $payload)->assertSessionHasErrors('capacity');

        // Solo notas y cupo: no se avisa.
        $this->actingAs($this->admin)->put(route('makeups.sessions.update', $block), ['capacity' => 6, 'notes' => 'Aula 3'] + $payload)
            ->assertSessionHas('success', 'Bloque actualizado.');
        Mail::assertNotQueued(MakeupSessionChangedMail::class);

        $this->actingAs($this->admin)->put(route('makeups.sessions.update', $block), ['capacity' => 6, 'session_date' => '2026-11-21', 'starts_at' => '09:00', 'ends_at' => '10:30'] + $payload)
            ->assertSessionHas('success', 'Bloque actualizado. Se avisó del cambio a 2 alumno(s) con reserva.');
        $this->assertSame('2026-11-21', $block->fresh()->session_date->toDateString());
        Mail::assertQueued(MakeupSessionChangedMail::class, 2);
        Mail::assertQueued(MakeupSessionChangedMail::class, fn (MakeupSessionChangedMail $mail) => $mail->makeupRequest->is($request)
            && $mail->change === 'rescheduled' && $mail->before['starts_at'] === '10:00' && $mail->hasTo('ana.rep@example.test'));
    }

    public function test_cancel_block_releases_bookings_and_notifies(): void
    {
        $block = $this->block('2026-11-20');
        $request = $this->bookStudent($block);

        $this->actingAs($this->admin)->post(route('makeups.sessions.cancel', $block), ['reason' => 'Profesora enferma'])
            ->assertSessionHas('success', 'Bloque cancelado. 1 alumno(s) con reserva quedaron avisados y pueden reservar otro horario.');

        $this->assertSame('cancelled', $block->fresh()->status);
        $this->assertStringContainsString('Profesora enferma', $block->fresh()->notes);
        $this->assertSame(MakeupRequest::STATUS_APPROVED_FOR_BOOKING, $request->fresh()->status, 'Vuelve a quedar por reservar.');
        $this->assertSame('cancelled', $request->fresh()->booking->status);
        Mail::assertQueued(MakeupSessionChangedMail::class, fn (MakeupSessionChangedMail $mail) => $mail->change === 'cancelled' && $mail->before['date'] === '2026-11-20');

        // Un bloque cancelado no se edita.
        $this->actingAs($this->admin)->put(route('makeups.sessions.update', $block), ['teacher_id' => $this->teacher->id, 'program_id' => $this->level->program_id, 'program_level_id' => $this->level->id, 'session_date' => '2026-11-22', 'starts_at' => '10:00', 'ends_at' => '11:00', 'capacity' => 5])
            ->assertSessionHasErrors('session');
        $this->assertSame('2026-11-20', $block->fresh()->session_date->toDateString());
    }

    public function test_other_campus_admin_cannot_touch_block(): void
    {
        $block = $this->block('2026-11-20');
        $other = Campus::create(['name' => 'La Cascada', 'code' => 'CAS', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'admin', 'campus_id' => $other->id, 'is_master' => false]);

        $this->actingAs($outsider)->post(route('makeups.sessions.cancel', $block))->assertForbidden();
        $this->assertSame('open', $block->fresh()->status);
    }

    public function test_course_and_enrollment_forms_load_only_selected_students(): void
    {
        Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Zoe', 'last_name' => 'Nolista', 'status' => 'active']);
        $picked = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Lia', 'last_name' => 'Elegida', 'status' => 'active']);

        $this->actingAs($this->admin)->get(route('enrollments.create', ['student_id' => $picked->id]))
            ->assertOk()->assertSee('Lia Elegida')->assertDontSee('Zoe Nolista')->assertSee('data-remote-url', false);
        $this->actingAs($this->admin)->get(route('courses.create'))
            ->assertOk()->assertDontSee('Zoe Nolista')->assertSee('data-remote-url', false);
    }
}
