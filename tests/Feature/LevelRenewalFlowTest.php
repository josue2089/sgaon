<?php

namespace Tests\Feature;

use App\Mail\LevelRenewalReminderMail;
use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\CampusProgramPrice;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\GradeEvaluationSet;
use App\Models\Group;
use App\Models\Period;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LevelRenewalService;
use App\Support\TodayInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LevelRenewalFlowTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $admin;

    private Teacher $teacher;

    private ScheduleTemplate $schedule;

    private Period $period;

    private ProgramLevel $level;

    private ProgramLevel $nextLevel;

    private Course $course;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00', config('app.timezone')));
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Filip', 'last_name' => 'Rachev', 'status' => 'active']);
        $this->schedule = ScheduleTemplate::create(['campus_id' => $this->campus->id, 'days' => ['mon', 'wed'], 'starts_at' => '16:00:00', 'ends_at' => '18:00:00', 'status' => 'active']);
        $this->period = Period::create(['campus_id' => $this->campus->id, 'code' => '2026-3', 'status' => 'active']);
        $this->level = ProgramLevel::query()->where('code', 'HS3A')->firstOrFail();
        $this->nextLevel = ProgramLevel::query()->where('code', 'HS3B')->firstOrFail();
        CampusProgramPrice::create(['campus_id' => $this->campus->id, 'program_id' => $this->level->program_id, 'amount' => 40, 'currency' => 'USD']);
        [$this->course, $this->group] = $this->courseOf($this->level, '2026-09-01', '2026-11-11', $this->teacher);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: Course, 1: Group} */
    private function courseOf(ProgramLevel $level, string $start, ?string $end, ?Teacher $teacher): array
    {
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $this->campus->id, 'name' => 'HighSchool'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher?->id,
            'period_id' => $this->period->id,
            'schedule_template_id' => $this->schedule->id,
            'name' => $level->name.' '.uniqid(),
            'start_date' => $start,
            'end_date' => $end,
            'academic_hours' => 40,
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'teacher_id' => $teacher?->id, 'name' => 'G'.uniqid(), 'status' => 'active', 'capacity' => 20]);
        $course->forceFill(['managed_group_id' => $group->id, 'end_date' => $end])->saveQuietly();

        return [$course->fresh(), $group];
    }

    private function enroll(string $name, array $attributes = []): Student
    {
        $student = Student::create($attributes + ['campus_id' => $this->campus->id, 'first_name' => $name, 'last_name' => 'Gil', 'status' => 'active']);
        Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $this->group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);

        return $student;
    }

    private function needSupport(Student $student): void
    {
        $this->grade($student, 'need_support');
    }

    private function grade(Student $student, string $rating = 'regular'): void
    {
        $enrollment = Enrollment::query()->where('student_id', $student->id)->where('group_id', $this->group->id)->sole();
        $set = GradeEvaluationSet::create(['campus_id' => $this->campus->id, 'course_id' => $this->course->id, 'group_id' => $this->group->id, 'evaluated_on' => '2026-11-01', 'title' => 'Final', 'created_by' => $this->admin->id]);
        GradeEntry::create(['grade_evaluation_set_id' => $set->id, 'campus_id' => $this->campus->id, 'enrollment_id' => $enrollment->id, 'vocabulary_rating' => $rating, 'listening_rating' => 'regular', 'speaking_rating' => 'regular', 'writing_rating' => 'regular', 'grammar_rating' => 'regular']);
    }

    private function representedBy(Student $student, string $email = 'marta@test.com'): User
    {
        $user = User::factory()->create(['role' => 'representative', 'email' => $email, 'campus_id' => $this->campus->id]);
        Representative::create(['campus_id' => $this->campus->id, 'user_id' => $user->id, 'first_name' => 'Marta', 'last_name' => 'Gil', 'email' => $email])->students()->attach($student->id);

        return $user;
    }

    private function openPayload(array $studentIds): array
    {
        return [
            'program_level_id' => $this->nextLevel->id, 'teacher_id' => $this->teacher->id, 'schedule_template_id' => $this->schedule->id,
            'period_id' => $this->period->id, 'start_date' => '2026-11-12', 'academic_hours' => 40, 'student_ids' => $studentIds,
        ];
    }

    public function test_open_next_level_creates_course_with_students_and_tuition(): void
    {
        $ana = $this->enroll('Ana');
        $luis = $this->enroll('Luis');
        $blocked = $this->enroll('Nora');
        $this->needSupport($blocked);
        $withdrawn = $this->enroll('Saul');
        Enrollment::query()->where('student_id', $withdrawn->id)->update(['status' => 'withdrawn']);

        $this->actingAs($this->admin)->get(route('courses.show', $this->course))->assertSee('Abrir siguiente nivel');
        $this->actingAs($this->admin)->get(route('courses.next-level.create', $this->course))
            ->assertOk()
            ->assertSee('value="2026-11-12"', false)
            ->assertSee('Ana Gil')
            ->assertSee('Need Support')
            ->assertSee('Aún sin evaluación final')
            ->assertDontSee('Saul Gil');

        $this->actingAs($this->admin)->post(route('courses.next-level.store', $this->course), $this->openPayload([$ana->id, $luis->id, $blocked->id, $withdrawn->id]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Siguiente nivel abierto. Alumnos inscritos: 2.')
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'Nora Gil'));

        $next = Course::query()->where('program_level_id', $this->nextLevel->id)->sole();
        $this->assertSame($this->teacher->id, $next->teacher_id);
        $this->assertSame('2026-11-12', $next->start_date->toDateString());
        $this->assertNotNull($next->managed_group_id);
        $this->assertEqualsCanonicalizing([$ana->id, $luis->id], Enrollment::query()->where('group_id', $next->managed_group_id)->pluck('student_id')->all());
        $this->assertSame(2, Charge::query()->where('course_id', $next->id)->where('charge_type', 'tuition')->count());
    }

    public function test_open_next_level_completes_the_draft_instead_of_duplicating(): void
    {
        [$draft] = $this->courseOf($this->nextLevel, '2026-11-12', null, null);

        $this->actingAs($this->admin)->post(route('courses.next-level.store', $this->course), $this->openPayload([]))->assertSessionHasNoErrors();

        $this->assertSame(1, Course::query()->where('program_level_id', $this->nextLevel->id)->count());
        $this->assertSame($this->teacher->id, $draft->fresh()->teacher_id);

        // Ya abierto: el botón lleva al curso en lugar de crear otro.
        $this->actingAs($this->admin)->get(route('courses.next-level.create', $this->course))->assertRedirect(route('courses.show', $draft));
    }

    public function test_skip_tuition_and_last_level_without_next(): void
    {
        $ana = $this->enroll('Ana');
        $this->actingAs($this->admin)->post(route('courses.next-level.store', $this->course), $this->openPayload([$ana->id]) + ['skip_tuition' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, Charge::query()->where('student_id', $ana->id)->count());

        $last = ProgramLevel::query()->where('code', 'CONVERSATIONAL2')->firstOrFail();
        [$lastCourse] = $this->courseOf($last, '2026-09-01', '2026-12-01', $this->teacher);
        $this->actingAs($this->admin)->get(route('courses.show', $lastCourse))->assertDontSee('Abrir siguiente nivel');
        $this->actingAs($this->admin)->get(route('courses.next-level.create', $lastCourse))->assertSessionHasErrors('next_level');
    }

    public function test_next_course_search_uses_a_window_and_accepts_a_teacher(): void
    {
        $renewals = app(LevelRenewalService::class);
        $this->assertNull($renewals->nextCourseFor($this->course));

        [$far] = $this->courseOf($this->nextLevel, '2026-12-03', null, $this->teacher);
        $this->assertNull($renewals->nextCourseFor($this->course), 'Empieza 22 días después: fuera de la ventana.');

        [$inside] = $this->courseOf($this->nextLevel, '2026-12-02', null, $this->teacher);
        $this->assertSame($inside->id, $renewals->nextCourseFor($this->course)->id);
        $this->assertNotSame($far->id, $inside->id);
    }

    public function test_portal_renewal_creates_enrollment_tuition_and_shows_in_daily_inbox(): void
    {
        $user = User::factory()->create(['role' => 'student', 'email' => 'ana@test.com', 'campus_id' => $this->campus->id]);
        $ana = $this->enroll('Ana', ['user_id' => $user->id, 'email' => 'ana@test.com']);
        $this->grade($ana);
        [$next, $nextGroup] = $this->courseOf($this->nextLevel, '2026-11-20', null, $this->teacher);

        $this->actingAs($user)->get(route('portal.student'))->assertOk()->assertSee($next->name);
        $this->actingAs($user)->post(route('portal.student.renewals.enroll', $next))->assertSessionHasNoErrors()->assertSessionHas('success');

        $enrollment = Enrollment::query()->where('group_id', $nextGroup->id)->where('student_id', $ana->id)->sole();
        $this->assertNotNull($enrollment->renewed_from_portal_at);
        $this->assertSame(1, Charge::query()->where('enrollment_id', $enrollment->id)->where('charge_type', 'tuition')->count());

        // Repetir no duplica.
        $this->actingAs($user)->post(route('portal.student.renewals.enroll', $next))->assertSessionHasNoErrors();
        $this->assertSame(1, Charge::query()->where('enrollment_id', $enrollment->id)->count());

        $counts = collect(TodayInbox::for($this->admin))->pluck('count', 'key');
        $this->assertSame(1, $counts['portal_renewals']);
        $this->actingAs($this->admin)->get(route('enrollments.index', ['source' => 'portal']))->assertOk()->assertSee('Ana Gil');
    }

    public function test_representative_renews_only_their_child_and_respects_need_support(): void
    {
        $ana = $this->enroll('Ana');
        $this->grade($ana);
        $blocked = $this->enroll('Nora');
        $this->needSupport($blocked);
        $other = $this->enroll('Otro');
        $user = $this->representedBy($ana);
        Representative::query()->where('user_id', $user->id)->sole()->students()->attach($blocked->id);
        [$next, $nextGroup] = $this->courseOf($this->nextLevel, '2026-11-20', null, $this->teacher);

        $this->actingAs($user)->get(route('portal.representative'))->assertOk()->assertSee('Renovar a Ana')->assertDontSee('Renovar a Nora');

        $this->actingAs($user)->post(route('portal.representative.renewals.enroll', [$ana, $next]))->assertSessionHasNoErrors();
        $this->assertTrue(Enrollment::query()->where('group_id', $nextGroup->id)->where('student_id', $ana->id)->exists());
        $this->assertSame(1, Charge::query()->where('student_id', $ana->id)->count());

        $this->actingAs($user)->post(route('portal.representative.renewals.enroll', [$other, $next]))->assertForbidden();
        $this->actingAs($user)->post(route('portal.representative.renewals.enroll', [$blocked, $next]))->assertSessionHasErrors('renewal');
        $this->assertSame(1, Enrollment::query()->where('group_id', $nextGroup->id)->count());
    }

    public function test_representative_with_children_in_two_campuses_can_use_both(): void
    {
        $ana = $this->enroll('Ana');
        $user = $this->representedBy($ana);
        $otherCampus = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $sibling = Student::create(['campus_id' => $otherCampus->id, 'first_name' => 'Leo', 'last_name' => 'Gil', 'status' => 'active']);
        Representative::query()->where('user_id', $user->id)->sole()->students()->attach($sibling->id);
        $charge = Charge::create(['campus_id' => $otherCampus->id, 'student_id' => $sibling->id, 'concept' => 'X', 'charge_type' => 'tuition', 'amount' => 10, 'currency' => 'USD', 'due_date' => today(), 'status' => 'pending']);

        $this->assertEqualsCanonicalizing([$this->campus->id, $otherCampus->id], $user->fresh()->allowedCampusIds());

        // Pasa el filtro de sede y llega a la validación del comprobante.
        $this->actingAs($user->fresh())->post(route('portal.representative.charges.payment', $charge), [])->assertSessionHasErrors('payment_proof');

        $stranger = Charge::create(['campus_id' => $otherCampus->id, 'student_id' => Student::create(['campus_id' => $otherCampus->id, 'first_name' => 'X', 'last_name' => 'Y', 'status' => 'active'])->id, 'concept' => 'Y', 'charge_type' => 'tuition', 'amount' => 10, 'currency' => 'USD', 'due_date' => today(), 'status' => 'pending']);
        $this->actingAs($user->fresh())->post(route('portal.representative.charges.payment', $stranger), [])->assertForbidden();
    }

    public function test_reminder_goes_to_student_and_representatives_with_their_portal_link(): void
    {
        $withOwn = $this->enroll('Ana', ['email' => 'ana@test.com']);
        $this->representedBy($withOwn, 'marta@test.com');
        $kid = $this->enroll('Nino');
        $this->representedBy($kid, 'pedro@test.com');
        $shared = $this->enroll('Luis', ['email' => 'carla@test.com']);
        $this->representedBy($shared, 'carla@test.com');
        foreach ([$withOwn, $kid, $shared] as $student) {
            $this->grade($student);
        }

        $this->artisan('levels:send-renewal-reminders')->assertSuccessful();

        Mail::assertQueued(LevelRenewalReminderMail::class, fn ($mail) => $mail->hasTo('ana@test.com') && ! $mail->forRepresentative);
        Mail::assertQueued(LevelRenewalReminderMail::class, fn ($mail) => $mail->hasTo('marta@test.com') && $mail->forRepresentative);
        Mail::assertQueued(LevelRenewalReminderMail::class, fn ($mail) => $mail->hasTo('pedro@test.com') && $mail->forRepresentative);
        Mail::assertQueued(LevelRenewalReminderMail::class, fn ($mail) => $mail->hasTo('carla@test.com') && $mail->forRepresentative);
        Mail::assertNotQueued(LevelRenewalReminderMail::class, fn ($mail) => $mail->hasTo('carla@test.com') && ! $mail->forRepresentative);

        $html = (new LevelRenewalReminderMail($kid, $this->course, $this->level, $this->nextLevel, null, true))->render();
        $this->assertStringContainsString('/portal/representative', $html);
        $this->assertStringContainsString('el curso de Nino Gil', $html);
    }
}
