<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Alert;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusScopeMultiCampusTest extends TestCase
{
    use RefreshDatabase;

    private Campus $picacho;

    private Campus $lecheria;

    private Campus $maturin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->picacho = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->lecheria = Campus::create(['name' => 'Lechería', 'code' => 'LEC', 'status' => 'active']);
        $this->maturin = Campus::create(['name' => 'Maturín', 'code' => 'MAT', 'status' => 'active']);
    }

    private function admin(Campus ...$campuses): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campuses[0]->id, 'is_master' => false]);
        if (count($campuses) > 1) {
            $admin->campuses()->attach(collect($campuses)->pluck('id'));
        }

        return $admin;
    }

    /** @return array{0: Group, 1: Enrollment, 2: ClassSession} */
    private function groupWithStudent(Campus $campus, string $name, ?Teacher $teacher = null): array
    {
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher?->id,
            'name' => 'Curso '.$name,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $campus->id, 'course_id' => $course->id, 'teacher_id' => $teacher?->id, 'name' => 'Grupo '.$name, 'status' => 'active', 'capacity' => 20]);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Alumno', 'last_name' => $name, 'status' => 'active']);
        $enrollment = Enrollment::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $session = ClassSession::create(['campus_id' => $campus->id, 'group_id' => $group->id, 'sequence' => 1, 'session_date' => today()->toDateString(), 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']);

        return [$group, $enrollment, $session];
    }

    public function test_admin_with_two_campuses_only_sees_those_two(): void
    {
        $this->groupWithStudent($this->picacho, 'Picacho');
        $this->groupWithStudent($this->lecheria, 'Lecheria');
        $this->groupWithStudent($this->maturin, 'Maturin');

        $admin = $this->admin($this->picacho, $this->lecheria);

        $this->actingAs($admin)->get(route('groups.index'))
            ->assertOk()
            ->assertSee('Grupo Picacho')
            ->assertSee('Grupo Lecheria')
            ->assertDontSee('Grupo Maturin');
        $this->actingAs($admin)->get(route('enrollments.index'))
            ->assertOk()
            ->assertDontSee('Alumno Maturin');
    }

    public function test_admin_cannot_create_data_in_a_campus_outside_their_scope(): void
    {
        [$picachoGroup] = $this->groupWithStudent($this->picacho, 'Base');
        $payload = fn (Campus $campus, string $name) => [
            'campus_id' => $campus->id, 'course_id' => $picachoGroup->course_id, 'name' => $name, 'status' => 'active', 'capacity' => 20,
        ];

        $two = $this->admin($this->picacho, $this->lecheria);
        $this->actingAs($two)->post(route('groups.store'), $payload($this->maturin, 'Intruso'))
            ->assertSessionHasErrors('campus_id');
        $this->assertDatabaseMissing('groups', ['name' => 'Intruso']);
        $this->actingAs($two)->post(route('groups.store'), $payload($this->lecheria, 'Permitido'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('groups', ['name' => 'Permitido', 'campus_id' => $this->lecheria->id]);

        $one = $this->admin($this->picacho);
        $this->actingAs($one)->post(route('groups.store'), $payload($this->maturin, 'Forzado'));
        $this->assertDatabaseHas('groups', ['name' => 'Forzado', 'campus_id' => $this->picacho->id]);
    }

    public function test_bell_shows_alerts_only_to_admins_and_attendance_to_the_teacher(): void
    {
        $teacherUser = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->picacho->id]);
        $teacher = Teacher::create(['campus_id' => $this->picacho->id, 'user_id' => $teacherUser->id, 'first_name' => 'Filip', 'last_name' => 'Rachev', 'status' => 'active']);
        [, $ownEnrollment] = $this->groupWithStudent($this->picacho, 'Propio', $teacher);
        [, $otherEnrollment] = $this->groupWithStudent($this->picacho, 'Ajeno');
        Alert::create(['campus_id' => $this->picacho->id, 'student_id' => $ownEnrollment->student_id, 'type' => 'attendance', 'status' => 'open', 'message' => 'Faltas de Propio']);
        Alert::create(['campus_id' => $this->picacho->id, 'student_id' => $ownEnrollment->student_id, 'type' => 'finance', 'status' => 'open', 'message' => 'Mora de Propio']);
        Alert::create(['campus_id' => $this->picacho->id, 'student_id' => $otherEnrollment->student_id, 'type' => 'attendance', 'status' => 'open', 'message' => 'Faltas de Ajeno']);

        $this->actingAs($teacherUser)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Faltas de Propio')
            ->assertDontSee('Mora de Propio')
            ->assertDontSee('Faltas de Ajeno')
            ->assertSee('Clases esta semana')
            ->assertSee('Asistencias pendientes')
            ->assertDontSee('Pagos del Mes');

        $studentUser = User::factory()->create(['role' => 'student', 'email' => 'alumno@test.com', 'campus_id' => $this->picacho->id]);
        Student::query()->whereKey($otherEnrollment->student_id)->update(['user_id' => $studentUser->id]);
        $this->actingAs($studentUser)->get(route('portal.student'))
            ->assertOk()
            ->assertDontSee('Mora de Propio')
            ->assertDontSee('Faltas de Propio');

        $this->actingAs($this->admin($this->picacho))->get(route('dashboard'))
            ->assertSee('Mora de Propio')
            ->assertSee('Pagos del Mes');
    }

    public function test_enrollment_with_history_is_withdrawn_not_deleted_and_campus_is_checked(): void
    {
        [, $withHistory, $session] = $this->groupWithStudent($this->picacho, 'Historial');
        AttendanceRecord::create(['class_session_id' => $session->id, 'enrollment_id' => $withHistory->id, 'status' => 'present']);
        [, $clean] = $this->groupWithStudent($this->picacho, 'Limpio');
        [, $foreign] = $this->groupWithStudent($this->maturin, 'Ajeno');
        $admin = $this->admin($this->picacho);

        $this->actingAs($admin)->delete(route('enrollments.destroy', $withHistory))->assertRedirect();
        $this->assertSame('withdrawn', $withHistory->fresh()->status);

        $this->actingAs($admin)->delete(route('enrollments.destroy', $clean))->assertRedirect();
        $this->assertNull($clean->fresh());

        $this->actingAs($admin)->delete(route('enrollments.destroy', $foreign))->assertForbidden();
        $this->assertNotNull($foreign->fresh());
    }
}
