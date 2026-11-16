<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\GradeEvaluationSet;
use App\Models\Group;
use App\Models\Teacher;
use App\Models\User;
use App\Support\GradeRubric;
use App\Support\RenewalEnrollmentEligibility;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_grade_evaluation_set(): void
    {
        $this->seed(UatFixtureSeeder::class);

        $teacherUser = User::where('email', 'teachera@uat.test')->firstOrFail();
        $teacher = Teacher::where('email', 'teachera@uat.test')->firstOrFail();
        $course = Course::firstOrFail();
        $group = Group::where('course_id', $course->id)->firstOrFail();
        $course->update([
            'teacher_id' => $teacher->id,
            'managed_group_id' => $group->id,
        ]);
        $course->refresh();
        $enrollment = Enrollment::firstOrFail();

        $ratingRow = [
            'vocabulary_rating' => 'outstanding',
            'listening_rating' => 'acceptable',
            'speaking_rating' => 'regular',
            'writing_rating' => 'acceptable',
            'grammar_rating' => 'need_support',
            'observations' => 'Buen avance.',
        ];

        $response = $this->actingAs($teacherUser)->post(route('courses.grades.store', $course), [
            'evaluated_on' => '2026-04-15',
            'title' => 'Reporte UAT',
            'entries' => [
                (string) $enrollment->id => $ratingRow,
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('grade_evaluation_sets', 1);
        $this->assertDatabaseCount('grade_entries', 1);

        $set = GradeEvaluationSet::firstOrFail();
        $this->assertSame((int) $course->id, (int) $set->course_id);
    }

    public function test_student_cannot_post_grade_evaluation(): void
    {
        $this->seed(UatFixtureSeeder::class);

        $studentUser = User::where('email', 'studenta@uat.test')->firstOrFail();
        $course = Course::firstOrFail();
        $enrollment = Enrollment::firstOrFail();

        $response = $this->actingAs($studentUser)->post(route('courses.grades.store', $course), [
            'evaluated_on' => '2026-04-15',
            'entries' => [
                (string) $enrollment->id => [
                    'vocabulary_rating' => 'outstanding',
                    'listening_rating' => 'acceptable',
                    'speaking_rating' => 'regular',
                    'writing_rating' => 'acceptable',
                    'grammar_rating' => 'need_support',
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('grade_evaluation_sets', 0);
    }

    public function test_student_portal_loads_after_grade_exists(): void
    {
        $this->seed(UatFixtureSeeder::class);

        $teacherUser = User::where('email', 'teachera@uat.test')->firstOrFail();
        $teacher = Teacher::where('email', 'teachera@uat.test')->firstOrFail();
        $studentUser = User::where('email', 'studenta@uat.test')->firstOrFail();
        $course = Course::firstOrFail();
        $group = Group::where('course_id', $course->id)->firstOrFail();
        $course->update([
            'teacher_id' => $teacher->id,
            'managed_group_id' => $group->id,
        ]);
        $course->refresh();
        $enrollment = Enrollment::firstOrFail();

        $this->actingAs($teacherUser)->post(route('courses.grades.store', $course), [
            'evaluated_on' => '2026-04-15',
            'title' => 'Informe',
            'publish' => '1',
            'entries' => [
                (string) $enrollment->id => [
                    'vocabulary_rating' => 'outstanding',
                    'listening_rating' => 'acceptable',
                    'speaking_rating' => 'regular',
                    'writing_rating' => 'acceptable',
                    'grammar_rating' => 'need_support',
                    'observations' => null,
                ],
            ],
        ])->assertRedirect();

        $entry = GradeEntry::firstOrFail();

        $portal = $this->actingAs($studentUser)->get(route('portal.student'));
        $portal->assertOk();
        $portal->assertSee('Mis evaluaciones', false);
        $portal->assertSee(GradeRubric::RATING_LABELS_ES[$entry->vocabulary_rating], false);
    }

    /** @return array{0: User, 1: Course, 2: Enrollment} */
    private function teacherCourse(): array
    {
        $this->seed(UatFixtureSeeder::class);
        $teacherUser = User::where('email', 'teachera@uat.test')->firstOrFail();
        $teacher = Teacher::where('email', 'teachera@uat.test')->firstOrFail();
        $course = Course::firstOrFail();
        $group = Group::where('course_id', $course->id)->firstOrFail();
        $course->update(['teacher_id' => $teacher->id, 'managed_group_id' => $group->id]);

        return [$teacherUser, $course->refresh(), Enrollment::where('group_id', $group->id)->where('status', 'active')->firstOrFail()];
    }

    public function test_partial_grades_save_as_draft(): void
    {
        [$teacherUser, $course, $enrollment] = $this->teacherCourse();

        $this->actingAs($teacherUser)->get(route('courses.grades.create', $course))
            ->assertOk()->assertSee('Sin nota')->assertSee('Guardar borrador')->assertSee('Guardar y publicar');

        $this->actingAs($teacherUser)->post(route('courses.grades.store', $course), [
            'evaluated_on' => '2026-04-15',
            'entries' => [$enrollment->id => ['vocabulary_rating' => 'outstanding', 'listening_rating' => '']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $set = GradeEvaluationSet::firstOrFail();
        $entry = GradeEntry::firstOrFail();
        $this->assertNull($set->published_at);
        $this->assertSame('outstanding', $entry->vocabulary_rating);
        $this->assertNull($entry->listening_rating);
        $this->assertFalse($entry->isComplete());

        $this->actingAs($teacherUser)->get(route('grade-evaluation-sets.show', $set))
            ->assertOk()->assertSee('Borrador')->assertSee('0 de')->assertSee('Publicar a las familias');
        $this->actingAs($teacherUser)->get(route('courses.grades.index', $course))
            ->assertOk()->assertSee('no visible para familias');
    }

    public function test_empty_submission_is_rejected(): void
    {
        [$teacherUser, $course, $enrollment] = $this->teacherCourse();

        $this->actingAs($teacherUser)->post(route('courses.grades.store', $course), [
            'evaluated_on' => '2026-04-15',
            'entries' => [$enrollment->id => ['vocabulary_rating' => '', 'observations' => '  ']],
        ])->assertSessionHasErrors('entries');
        $this->assertDatabaseCount('grade_evaluation_sets', 0);
    }

    public function test_portals_show_only_published_grades(): void
    {
        [$teacherUser, $course, $enrollment] = $this->teacherCourse();
        $studentUser = User::where('email', 'studenta@uat.test')->firstOrFail();
        $repUser = User::where('email', 'repa@uat.test')->firstOrFail();

        $this->actingAs($teacherUser)->post(route('courses.grades.store', $course), [
            'evaluated_on' => '2026-04-15',
            'title' => 'Informe de prueba 447',
            'entries' => [$enrollment->id => ['speaking_rating' => 'regular', 'observations' => 'Nota privada del borrador']],
        ])->assertRedirect();
        $set = GradeEvaluationSet::firstOrFail();

        foreach ([[$studentUser, 'portal.student'], [$repUser, 'portal.representative']] as [$user, $route]) {
            $this->actingAs($user)->get(route($route))->assertOk()
                ->assertDontSee('Informe de prueba 447')->assertDontSee('Nota privada del borrador');
        }

        $this->actingAs($teacherUser)->post(route('grade-evaluation-sets.publish', $set))->assertRedirect();
        $this->assertNotNull($set->refresh()->published_at);
        $this->assertSame((int) $teacherUser->id, (int) $set->published_by);

        foreach ([[$studentUser, 'portal.student'], [$repUser, 'portal.representative']] as [$user, $route]) {
            $this->actingAs($user)->get(route($route))->assertOk()
                ->assertSee('Informe de prueba 447')->assertSee('Nota privada del borrador');
        }

        $this->actingAs($teacherUser)->delete(route('grade-evaluation-sets.unpublish', $set))->assertRedirect();
        $this->assertNull($set->refresh()->published_at);
        $this->actingAs($repUser)->get(route('portal.representative'))->assertOk()->assertDontSee('Informe de prueba 447');
    }

    public function test_edit_completes_or_clears_students_and_can_publish(): void
    {
        [$teacherUser, $course, $enrollment] = $this->teacherCourse();

        $this->actingAs($teacherUser)->post(route('courses.grades.store', $course), [
            'evaluated_on' => '2026-04-15',
            'entries' => [$enrollment->id => ['grammar_rating' => 'acceptable']],
        ]);
        $set = GradeEvaluationSet::firstOrFail();

        $this->actingAs($teacherUser)->get(route('grade-evaluation-sets.edit', $set))->assertOk()->assertSee('Guardar y publicar');

        $full = array_fill_keys(['vocabulary_rating', 'listening_rating', 'speaking_rating', 'writing_rating', 'grammar_rating'], 'acceptable');
        $this->actingAs($teacherUser)->put(route('grade-evaluation-sets.update', $set), [
            'evaluated_on' => '2026-04-16',
            'publish' => '1',
            'entries' => [$enrollment->id => $full],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(GradeEntry::firstOrFail()->isComplete());
        $this->assertNotNull($set->refresh()->published_at);

        // Borrar todas las notas de la única fila no deja la evaluación vacía.
        $this->actingAs($teacherUser)->put(route('grade-evaluation-sets.update', $set), [
            'evaluated_on' => '2026-04-16',
            'entries' => [$enrollment->id => ['vocabulary_rating' => '']],
        ])->assertSessionHasErrors('entries');
        $this->assertDatabaseCount('grade_entries', 1);
    }

    public function test_publish_requires_entries_and_course_access(): void
    {
        [$teacherUser, $course] = $this->teacherCourse();
        $set = GradeEvaluationSet::create(['campus_id' => $course->campus_id, 'course_id' => $course->id, 'evaluated_on' => '2026-04-15']);

        $this->actingAs($teacherUser)->post(route('grade-evaluation-sets.publish', $set))->assertSessionHasErrors('publish');
        $this->assertNull($set->refresh()->published_at);

        $studentUser = User::where('email', 'studenta@uat.test')->firstOrFail();
        $this->actingAs($studentUser)->post(route('grade-evaluation-sets.publish', $set))->assertForbidden();
    }

    public function test_incomplete_draft_does_not_decide_renewal(): void
    {
        [, $course, $enrollment] = $this->teacherCourse();
        $student = $enrollment->student;
        $make = fn (string $date, array $ratings) => GradeEntry::create([
            'grade_evaluation_set_id' => GradeEvaluationSet::create(['campus_id' => $course->campus_id, 'course_id' => $course->id, 'evaluated_on' => $date])->id,
            'campus_id' => $course->campus_id,
            'enrollment_id' => $enrollment->id,
        ] + $ratings);

        $make('2026-04-01', array_fill_keys(['vocabulary_rating', 'listening_rating', 'speaking_rating', 'writing_rating', 'grammar_rating'], 'acceptable'));
        $make('2026-05-01', ['vocabulary_rating' => 'need_support']);

        $result = RenewalEnrollmentEligibility::evaluateForCourse($student, $course);
        $this->assertTrue($result['eligible'], 'La última evaluación completa es Acceptable; el borrador a medias no cuenta.');
    }
}
