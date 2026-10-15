<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Period;
use App\Models\ProgramLevel;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\CoursePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SetCourseStartDateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_moves_start_earlier_keeping_attended_classes(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-10-15 10:00:00');

        // Caso HS2A L-M 2.20 3.50 (Cascada): en el sistema empieza el 27/05; empezó el 20/05.
        $campus = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
        $teacher = Teacher::create(['campus_id' => $campus->id, 'first_name' => 'Silvestre', 'last_name' => 'Arismendi', 'status' => 'active']);
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher->id,
            'period_id' => Period::create(['campus_id' => $campus->id, 'code' => '2026-Q2', 'status' => 'active'])->id,
            'schedule_template_id' => ScheduleTemplate::create(['campus_id' => $campus->id, 'days' => ['mon', 'wed'], 'starts_at' => '14:20:00', 'ends_at' => '15:50:00', 'status' => 'active'])->id,
            'academic_hours' => 30,
            'name' => 'HS2A - Horario: L-M 2.20 3.50',
            'start_date' => '2026-05-27',
            'status' => 'active',
        ]);
        $course = CoursePlanner::sync($course->fresh(['teacher', 'period', 'scheduleTemplate', 'managedGroup.sessions.attendanceRecords']), true);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'status' => 'active']);
        $enrollment = Enrollment::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'group_id' => $course->managed_group_id, 'status' => 'active', 'progress' => 0]);

        $sessions = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->get();
        $total = $sessions->count();
        // Como en producción: hay asistencia desde la 6.ª clase y las últimas clases quedan libres.
        $attended = $sessions->slice(5, $total - 8);
        foreach ($attended as $session) {
            AttendanceRecord::create(['class_session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'present']);
        }
        $attendedDates = $attended->map(fn ($session) => $session->session_date->toDateString())->values()->all();

        $this->artisan('courses:set-start-date', ['course' => $course->id, 'date' => '2026-05-20', '--dry-run' => true])
            ->expectsOutputToContain('[dry-run] No se guardó nada.')
            ->assertSuccessful();
        $this->assertSame('2026-05-27', $course->fresh()->start_date->toDateString());

        $this->artisan('courses:set-start-date', ['course' => $course->id, 'date' => '2026-05-20'])
            ->expectsOutputToContain('Fecha de inicio actualizada.')
            ->assertSuccessful();

        $after = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->get();
        $this->assertSame('2026-05-20', $course->fresh()->start_date->toDateString());
        $this->assertSame('2026-05-20', $after->first()->session_date->toDateString());
        $this->assertSame('2026-05-25', $after->get(1)->session_date->toDateString());
        $this->assertSame($total, $after->count(), 'Misma cantidad de clases.');
        foreach ($attendedDates as $date) {
            $this->assertTrue($after->contains(fn ($session) => $session->session_date->toDateString() === $date), "Se conserva la clase con asistencia del {$date}.");
        }
        $this->assertSame(count($attendedDates), AttendanceRecord::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'course.start_date_changed')->exists());
    }
}
