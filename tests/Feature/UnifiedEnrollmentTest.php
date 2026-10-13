<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UnifiedEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $this->student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'status' => 'active']);
    }

    private function group(string $name, ?Campus $campus = null, string $status = 'active'): Group
    {
        $campus ??= $this->campus;
        $level = ProgramLevel::query()->firstOrFail();
        $level->update(['base_price_eur' => 240]);
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => $name,
            'start_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $campus->id, 'course_id' => $course->id, 'name' => $name.'-G', 'status' => $status, 'capacity' => 20]);
        $course->update(['managed_group_id' => $group->id]);

        return $group;
    }

    public function test_enroll_modal_lists_only_active_groups_of_the_student_campus(): void
    {
        $this->group('HS2B Lunes');
        $this->group('HS3A Inactivo', null, 'inactive');
        $this->group('HS4A Otra sede', Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']));
        $enrolled = $this->group('HS1A Ya inscrito');
        Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'group_id' => $enrolled->id, 'status' => 'active', 'progress' => 0]);

        $this->actingAs($this->admin)->get(route('students.show', $this->student))
            ->assertOk()
            ->assertSee('id="enroll-modal"', false)
            ->assertSee('HS2B Lunes')
            ->assertSee('20 cupos')
            ->assertSee('No generar mensualidad')
            ->assertDontSee('HS3A Inactivo')
            ->assertDontSee('HS4A Otra sede')
            ->assertDontSee('data-search="hs1a ya inscrito', false);
    }

    public function test_enrolling_from_the_student_page_returns_to_it_and_bills_tuition(): void
    {
        $group = $this->group('HS2B Lunes');

        $this->actingAs($this->admin)->post(route('enrollments.store'), [
            'student_id' => $this->student->id,
            'group_id' => $group->id,
            'status' => 'active',
            'return_to' => 'student',
        ])->assertRedirect(route('students.show', $this->student).'#academico')
            ->assertSessionHas('success', 'Inscrito en HS2B Lunes.');

        $this->assertTrue(Enrollment::query()->where('student_id', $this->student->id)->where('group_id', $group->id)->exists());
        $this->assertTrue(Charge::query()->where('student_id', $this->student->id)->exists(), 'Genera la mensualidad.');
    }

    public function test_skip_tuition_and_errors_reopen_the_modal(): void
    {
        $group = $this->group('HS2B Lunes');
        $inactive = $this->group('HS3A Inactivo', null, 'inactive');

        $this->actingAs($this->admin)->post(route('enrollments.store'), [
            'student_id' => $this->student->id, 'group_id' => $group->id, 'status' => 'active', 'return_to' => 'student', 'skip_tuition' => 1,
        ])->assertRedirect();
        $this->assertFalse(Charge::query()->where('student_id', $this->student->id)->exists());

        $other = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Luis', 'last_name' => 'Gil', 'status' => 'active']);
        $this->actingAs($this->admin)
            ->from(route('students.show', $other))
            ->followingRedirects()
            ->post(route('enrollments.store'), ['student_id' => $other->id, 'group_id' => $inactive->id, 'status' => 'active', 'return_to' => 'student'])
            ->assertOk()
            ->assertSee('id="enroll-modal" class="ui-modal ui-modal--md" data-open-on-load="1"', false);
    }

    public function test_other_entry_points_use_searchable_student_selects(): void
    {
        $this->actingAs($this->admin)->get(route('enrollments.create', ['student_id' => $this->student->id]))
            ->assertOk()
            ->assertSee('id="enrollment-student-search"', false)
            ->assertSee('id="enrollment-group-search"', false);

        $this->actingAs($this->admin)->get(route('makeups.index'))
            ->assertOk()
            ->assertSee('id="makeup-student-filter-search"', false);

        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->actingAs($master)->get(route('courses.create'))
            ->assertOk()
            ->assertSee('id="course-initial-students-search"', false);
    }

    public function test_creating_a_student_opens_its_page_and_form_has_collapsible_sections(): void
    {
        $this->actingAs($this->admin)->get(route('students.create'))
            ->assertOk()
            ->assertSee('<details class="card mt-2 form-section" open>', false)
            ->assertSee('Datos comerciales');

        $response = $this->actingAs($this->admin)->post(route('students.store'), [
            'campus_id' => $this->campus->id,
            'first_name' => 'Nueva',
            'last_name' => 'Alumna',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $created = Student::query()->where('first_name', 'Nueva')->firstOrFail();
        $response->assertRedirect(route('students.show', $created))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'inscríbelo en un curso'));
    }
}
