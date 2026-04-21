<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\GradeEvaluationSet;
use App\Models\Group;
use App\Models\Teacher;
use App\Models\User;
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
        $portal->assertSee(\App\Support\GradeRubric::RATING_LABELS_ES[$entry->vocabulary_rating], false);
    }
}
