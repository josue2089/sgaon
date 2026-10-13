<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtracurricularStudentSheetTest extends TestCase
{
    use RefreshDatabase;

    private function master(Campus $campus): User
    {
        return User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
    }

    private function studentPayload(Campus $campus, Program $program, array $extra = []): array
    {
        return array_merge([
            'campus_id' => $campus->id,
            'registration_program_id' => $program->id,
            'first_name' => 'Sofía',
            'last_name' => 'Rojas',
            'birth_date' => now()->subYears(9)->toDateString(),
            'status' => 'active',
            'representative' => [
                'first_name' => 'María',
                'last_name' => 'Rojas',
                'document_id' => '12345678',
                'nationality' => 'V',
                'relation' => 'Madre',
                'mobile_phone' => '0414-0000000',
            ],
        ], $extra);
    }

    public function test_admin_saves_extracurricular_fields_and_sees_them_on_student_page(): void
    {
        $campus = Campus::create(['name' => 'Mater Dei', 'code' => 'AEMD', 'status' => 'active']);
        $program = Program::create(['name' => 'Danza', 'code' => 'DANZA', 'status' => 'active', 'is_extracurricular' => true]);
        $master = $this->master($campus);

        $this->actingAs($master)->get(route('students.create'))
            ->assertOk()
            ->assertSee('data-extracurricular="1"', false);

        $this->actingAs($master)
            ->post(route('students.store'), $this->studentPayload($campus, $program, [
                'school_grade' => '4to grado',
                'school_section' => 'U',
                'emergency_phone' => '0412-1111111',
                'extracurricular_level' => 'Básico',
                'extracurricular_objectives' => 'Coordinación y ritmo',
                'payment_condition' => '10 cuotas de 35 USD',
            ]))
            ->assertRedirect(route('students.show', Student::query()->where('first_name', 'Sofía')->value('id')));

        $student = Student::query()->where('first_name', 'Sofía')->firstOrFail();
        $this->assertSame('4to grado', $student->school_grade);
        $this->assertSame('U', $student->school_section);
        $this->assertSame('0412-1111111', $student->emergency_phone);
        $this->assertSame('10 cuotas de 35 USD', $student->payment_condition);
        $this->assertTrue($student->isExtracurricular());

        $representative = $student->representatives()->firstOrFail();
        $this->assertSame('V', $representative->nationality);
        $this->assertSame('Madre', $representative->relation);

        $this->actingAs($master)->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Actividad extracurricular')
            ->assertSee('4to grado')
            ->assertSee('9 años')
            ->assertSee('V-12345678')
            ->assertSee('Coordinación y ritmo');

        $this->actingAs($master)->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('id="extracurricular-section">', false);
    }

    public function test_regular_student_does_not_show_extracurricular_section(): void
    {
        $campus = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $program = Program::query()->where('is_extracurricular', false)->firstOrFail();
        $master = $this->master($campus);

        $this->actingAs($master)->post(route('students.store'), $this->studentPayload($campus, $program));
        $student = Student::query()->where('first_name', 'Sofía')->firstOrFail();

        $this->assertFalse($student->isExtracurricular());
        $this->assertNull($student->extracurricular_objectives);
        $this->assertSame('Madre', $student->representatives()->firstOrFail()->relation);

        $this->actingAs($master)->get(route('students.show', $student))
            ->assertOk()
            ->assertDontSee('Actividad extracurricular');
        $this->actingAs($master)->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('id="extracurricular-section" hidden>', false);
    }

    public function test_course_teacher_can_edit_observations_and_other_teacher_cannot(): void
    {
        $this->seed(UatFixtureSeeder::class);

        $program = Program::create(['name' => 'Robótica colegio', 'code' => 'ROB-COL', 'status' => 'active', 'is_extracurricular' => true]);
        $teacherUser = User::where('email', 'teachera@uat.test')->firstOrFail();
        $teacher = Teacher::where('email', 'teachera@uat.test')->firstOrFail();
        $course = Course::query()->where('teacher_id', $teacher->id)->firstOrFail();
        $course->update(['program_id' => $program->id]);
        $enrollment = Enrollment::query()
            ->where('status', 'active')
            ->whereHas('group', fn ($q) => $q->where('course_id', $course->id))
            ->firstOrFail();
        $student = $enrollment->student;
        $this->assertTrue($student->isExtracurricular());

        $this->actingAs($teacherUser)->get(route('courses.grades.index', $course))
            ->assertOk()
            ->assertSee('Observaciones del docente');
        $this->actingAs($teacherUser)->get(route('courses.observations.edit', $course))
            ->assertOk()
            ->assertSee($student->first_name);

        $outsider = Student::query()->whereKeyNot($student->id)->firstOrFail();
        $this->actingAs($teacherUser)
            ->put(route('courses.observations.update', $course), [
                'observations' => [
                    $student->id => 'Participa mucho, reforzar vocabulario.',
                    $outsider->id => 'No debería guardarse',
                ],
            ])
            ->assertRedirect(route('courses.observations.edit', $course));

        $this->assertSame('Participa mucho, reforzar vocabulario.', $student->fresh()->teacher_observations);
        $this->assertNull($outsider->fresh()->teacher_observations);

        $otherTeacherUser = User::factory()->create(['campus_id' => $teacherUser->campus_id, 'role' => 'teacher', 'email' => 'teacherz@uat.test']);
        $otherTeacherUser->roles()->syncWithoutDetaching([Role::where('name', 'teacher')->value('id')]);
        Teacher::create([
            'campus_id' => $teacherUser->campus_id,
            'user_id' => $otherTeacherUser->id,
            'first_name' => 'Otro',
            'last_name' => 'Profesor',
            'email' => $otherTeacherUser->email,
            'status' => 'active',
        ]);

        $this->actingAs($otherTeacherUser)
            ->put(route('courses.observations.update', $course), ['observations' => [$student->id => 'Intento']])
            ->assertForbidden();
        $this->assertSame('Participa mucho, reforzar vocabulario.', $student->fresh()->teacher_observations);
    }
}
