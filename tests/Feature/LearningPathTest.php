<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use App\Support\LearningPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LearningPathTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00', config('app.timezone')));
        $this->artisan('programs:apply-cefr-structure')->assertSuccessful();
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enrolledIn(string $levelCode, array $attributes = []): Student
    {
        $level = ProgramLevel::query()->where('code', $levelCode)->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $this->campus->id, 'name' => 'Nivel'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'name' => 'Curso '.$levelCode.' '.uniqid(),
            'start_date' => '2026-10-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'name' => 'G'.$levelCode.uniqid(), 'status' => 'active', 'capacity' => 20]);
        foreach (['2026-10-05', '2026-11-02', '2026-11-10', '2026-11-16', '2026-11-23'] as $i => $date) {
            ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => $i + 1, 'session_date' => $date, 'starts_at' => '16:00:00', 'ends_at' => '17:00:00']);
        }
        $student = Student::create($attributes + ['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'status' => 'active']);
        Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-10-01', 'status' => 'active', 'progress' => 0]);

        return $student;
    }

    public function test_path_has_current_level_progress_next_and_route(): void
    {
        $path = LearningPath::for($this->enrolledIn('HS3A'));

        $this->assertSame('HS3A', $path['level']->code);
        $this->assertSame('HS3B', $path['next']->code);
        $this->assertSame([3, 5], [$path['sessions_done'], $path['sessions_total']]);
        $states = collect($path['levels'])->mapWithKeys(fn ($item) => [$item['level']->code => $item['state']]);
        $this->assertSame('done', $states['HS2B']);
        $this->assertSame('current', $states['HS3A']);
        $this->assertSame('upcoming', $states['HS6B']);
        $this->assertNull($path['adult_transition']);
    }

    public function test_teen_of_fourteen_sees_the_equivalent_adult_level(): void
    {
        $path = LearningPath::for($this->enrolledIn('HS3B', ['birth_date' => '2012-01-15']));

        $this->assertSame('IU4A', $path['adult_transition']?->code);
        $this->assertNull(LearningPath::for($this->enrolledIn('HS3B', ['birth_date' => '2013-01-15']))['adult_transition']);
    }

    public function test_programs_without_cefr_and_students_without_course_have_no_path(): void
    {
        $this->assertNull(LearningPath::for(Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Sin', 'last_name' => 'Curso', 'status' => 'active'])));

        $student = $this->enrolledIn('HS3A');
        \App\Models\Program::query()->where('code', 'HS')->update(['uses_cefr' => false]);
        $this->assertNull(LearningPath::for($student));
    }

    public function test_student_and_representative_portals_show_the_path(): void
    {
        $user = User::factory()->create(['role' => 'student', 'email' => 'ana@test.com', 'campus_id' => $this->campus->id]);
        $student = $this->enrolledIn('HS3B', ['user_id' => $user->id, 'email' => 'ana@test.com', 'birth_date' => '2011-05-01']);
        $parent = User::factory()->create(['role' => 'representative', 'email' => 'marta@test.com', 'campus_id' => $this->campus->id]);
        Representative::create(['campus_id' => $this->campus->id, 'user_id' => $parent->id, 'first_name' => 'Marta', 'last_name' => 'Gil', 'email' => 'marta@test.com'])->students()->attach($student->id);

        foreach ([[$user, 'portal.student'], [$parent, 'portal.representative']] as [$who, $route]) {
            $this->actingAs($who)->get(route($route))
                ->assertOk()
                ->assertSee('Ruta de aprendizaje')
                ->assertSee('CEFR B1 Teens · GSE 43–50')
                ->assertSee('3/5')
                ->assertSee('IU 4A');
        }
    }
}
