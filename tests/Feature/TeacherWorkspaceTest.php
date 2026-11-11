<?php

namespace Tests\Feature;

use App\Mail\TeacherDailyMail;
use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\MakeupBooking;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\ProgramLevel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\MakeupRecoveryEngine;
use App\Support\TodayInbox;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** #446: mis recuperativas, clases pasadas sin asistencia, correo diario y una sola regla de propiedad. */
class TeacherWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $teacherUser;

    private Teacher $teacher;

    private Teacher $otherTeacher;

    private User $otherTeacherUser;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-10-14 06:30:00');

        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->teacherUser = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id, 'email' => 'sofia@test.com']);
        $this->teacher = Teacher::create(['campus_id' => $this->campus->id, 'user_id' => $this->teacherUser->id, 'first_name' => 'Sofia', 'last_name' => 'Baron', 'status' => 'active']);
        $this->otherTeacherUser = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id, 'email' => 'otro@test.com']);
        $this->otherTeacher = Teacher::create(['campus_id' => $this->campus->id, 'user_id' => $this->otherTeacherUser->id, 'first_name' => 'Otro', 'last_name' => 'Prof', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Curso con un alumno activo y clases en las fechas dadas. El grupo puede tener otro profesor que el titular. */
    private function course(?Teacher $titular, ?Teacher $groupTeacher, array $dates, string $name = 'Ana'): array
    {
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $titular?->id,
            'name' => 'HS5A '.$name,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $this->campus->id, 'course_id' => $course->id, 'teacher_id' => $groupTeacher?->id, 'name' => 'G-'.$name, 'status' => 'active', 'capacity' => 30]);
        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => $name, 'last_name' => 'Test', 'status' => 'active']);
        $enrollment = Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => 'active', 'progress' => 0]);
        $sessions = collect($dates)->values()->map(fn ($date, $i) => ClassSession::create(['campus_id' => $this->campus->id, 'group_id' => $group->id, 'sequence' => $i + 1, 'session_date' => $date, 'starts_at' => '16:00:00', 'ends_at' => '17:30:00']));

        return [$course, $enrollment, $sessions];
    }

    private function bookedMakeup(Enrollment $enrollment, ClassSession $missed, Teacher $teacher, string $date): MakeupBooking
    {
        $record = AttendanceRecord::create(['class_session_id' => $missed->id, 'enrollment_id' => $enrollment->id, 'status' => 'absent']);
        $request = MakeupRecoveryEngine::syncForAttendanceRecord($record);
        $request->forceFill(['status' => MakeupRequest::STATUS_APPROVED_FOR_BOOKING])->save();
        $level = ProgramLevel::query()->firstOrFail();
        $session = MakeupSession::create(['campus_id' => $this->campus->id, 'teacher_id' => $teacher->id, 'program_id' => $level->program_id, 'program_level_id' => $level->id, 'session_date' => $date, 'starts_at' => '10:00:00', 'ends_at' => '11:30:00', 'capacity' => 5, 'status' => 'open']);

        return MakeupRecoveryEngine::book($request->fresh(), $session);
    }

    public function test_teacher_marks_attendance_only_on_own_makeups(): void
    {
        [, $enrollment, $sessions] = $this->course($this->teacher, $this->teacher, ['2026-10-07']);
        $past = $this->bookedMakeup($enrollment, $sessions[0], $this->teacher, '2026-10-13');

        [, $otherEnrollment, $otherSessions] = $this->course($this->otherTeacher, $this->otherTeacher, ['2026-10-07'], 'Nora');
        $foreign = $this->bookedMakeup($otherEnrollment, $otherSessions[0], $this->otherTeacher, '2026-10-13');

        $this->actingAs($this->teacherUser)->get(route('teacher.makeups.index'))
            ->assertOk()
            ->assertSee('Ana Test')
            ->assertDontSee('Nora Test')
            ->assertSee(route('teacher.makeups.bookings.update', $past), false);

        $this->actingAs($this->teacherUser)->patch(route('teacher.makeups.bookings.update', $past), ['status' => 'attended'])->assertRedirect();
        $this->assertSame('attended', $past->fresh()->status);
        $this->assertSame(MakeupRequest::STATUS_COMPLETED, $past->makeupRequest->fresh()->status);

        $this->actingAs($this->teacherUser)->patch(route('teacher.makeups.bookings.update', $foreign), ['status' => 'missed'])->assertForbidden();
        $this->assertSame('reserved', $foreign->fresh()->status);

        // Admin no entra a "Mis recuperativas"; tiene su propia pantalla.
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id]);
        $this->actingAs($admin)->get(route('teacher.makeups.index'))->assertForbidden();
    }

    public function test_future_makeup_cannot_be_marked_yet(): void
    {
        [, $enrollment, $sessions] = $this->course($this->teacher, $this->teacher, ['2026-10-07']);
        $future = $this->bookedMakeup($enrollment, $sessions[0], $this->teacher, '2026-10-16');

        $this->actingAs($this->teacherUser)->patch(route('teacher.makeups.bookings.update', $future), ['status' => 'attended'])
            ->assertSessionHasErrors('status');
        $this->assertSame('reserved', $future->fresh()->status);
    }

    public function test_past_classes_without_attendance_appear_in_today_inbox(): void
    {
        // Hoy, hace 3 días (sin asistencia), hace 5 (con asistencia) y hace 20 (fuera de la ventana de 14 días).
        [, $enrollment, $sessions] = $this->course($this->teacher, $this->teacher, ['2026-10-14', '2026-10-11', '2026-10-09', '2026-09-24']);
        AttendanceRecord::create(['class_session_id' => $sessions[2]->id, 'enrollment_id' => $enrollment->id, 'status' => 'present']);
        $this->course($this->otherTeacher, $this->otherTeacher, ['2026-10-10'], 'Nora');

        $counts = collect(TodayInbox::for($this->teacherUser))->pluck('count', 'key')->all();
        $this->assertSame(1, $counts['attendance']);
        $this->assertSame(1, $counts['attendance_past'], 'Solo la suya de hace 3 días.');

        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $this->assertSame(2, collect(TodayInbox::for($admin))->pluck('count', 'key')->all()['attendance_past'], 'El admin ve las de todos los profesores de su sede.');

        $this->actingAs($this->teacherUser)->get(route('attendance.index', ['pending' => 1]))
            ->assertOk()
            ->assertSee('Sin asistencia (últimos 14 días)')
            ->assertSee('11/10/2026')
            ->assertDontSee('09/10/2026')
            ->assertDontSee('10/10/2026');
    }

    public function test_course_titular_and_group_teacher_share_the_same_ownership_rule(): void
    {
        // Titular del curso = Sofía, pero el grupo lo dicta otro profesor.
        [$course, , $sessions] = $this->course($this->teacher, $this->otherTeacher, ['2026-10-14']);

        $this->assertTrue($this->teacher->ownsCourse($course));
        $this->assertTrue($this->otherTeacher->ownsCourse($course));

        // Los dos pueden abrir la asistencia y las notas del curso.
        foreach ([$this->teacherUser, $this->otherTeacherUser] as $user) {
            $this->actingAs($user)->get(route('attendance.index', ['class_session_id' => $sessions[0]->id]))->assertOk()->assertSee('HS5A Ana');
            $this->actingAs($user)->get(route('courses.grades.index', $course))->assertOk();
        }

        $stranger = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]);
        Teacher::create(['campus_id' => $this->campus->id, 'user_id' => $stranger->id, 'first_name' => 'Ajeno', 'last_name' => 'X', 'status' => 'active']);
        $this->actingAs($stranger)->get(route('courses.grades.index', $course))->assertForbidden();
        $this->actingAs($stranger)->post(route('attendance.store'), ['class_session_id' => $sessions[0]->id, 'records' => [['enrollment_id' => Enrollment::query()->value('id'), 'status' => 'present']]])->assertForbidden();
    }

    public function test_daily_mail_goes_to_each_teacher_with_only_their_data(): void
    {
        // Hoy, una pasada sin asistencia (13/10) y una con la falta que generó la recuperativa (12/10).
        [, $enrollment, $sessions] = $this->course($this->teacher, $this->teacher, ['2026-10-14', '2026-10-12', '2026-10-13']);
        $this->bookedMakeup($enrollment, $sessions[1], $this->teacher, '2026-10-15');
        $this->course($this->otherTeacher, $this->otherTeacher, ['2026-10-14'], 'Nora');
        // Profesor sin clases ni pendientes: no recibe correo.
        $idle = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id, 'email' => 'libre@test.com']);
        Teacher::create(['campus_id' => $this->campus->id, 'user_id' => $idle->id, 'first_name' => 'Libre', 'last_name' => 'Y', 'status' => 'active']);

        $this->artisan('teachers:send-daily-summary --dry-run')->expectsOutputToContain('[dry-run] Correos: 2.')->assertSuccessful();
        Mail::assertNothingQueued();

        $this->artisan('teachers:send-daily-summary')->expectsOutputToContain('Correos: 2. Sin nada que avisar o sin ficha: 1.')->assertSuccessful();

        Mail::assertQueued(TeacherDailyMail::class, 2);
        Mail::assertNotQueued(TeacherDailyMail::class, fn (TeacherDailyMail $mail) => $mail->hasTo('libre@test.com'));
        Mail::assertQueued(TeacherDailyMail::class, function (TeacherDailyMail $mail) {
            if (! $mail->hasTo('sofia@test.com')) {
                return false;
            }
            $html = $mail->render();

            return $mail->todaySessions->count() === 1
                && $mail->makeupBookings->count() === 1
                && in_array('attendance_past', array_column($mail->items, 'key'), true)
                && str_contains($html, 'HS5A Ana')
                && str_contains($html, 'Ana Test')
                && ! str_contains($html, 'HS5A Nora')
                && $mail->envelope()->subject === 'Tu día en ON English: 1 clase hoy';
        });

        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'teachers:send-daily-summary'));
        $this->assertCount(1, $events);
        $this->assertSame('0 7 * * *', $events->first()->expression);
    }
}
