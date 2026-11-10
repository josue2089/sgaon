<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CoursePlanner;
use App\Support\LessonTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** #470: plan de clases oficial (File A/B), estructura de clase y bitácora del alumno. */
class LessonFileTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
    }

    private function level(string $code): ProgramLevel
    {
        return ProgramLevel::query()->where('code', $code)->firstOrFail();
    }

    /** Curso de HS3A con calendario generado y un alumno inscrito con su representante. */
    private function courseWithStudent(): array
    {
        $level = $this->level('HS3A');
        $teacher = Teacher::create(['campus_id' => $this->campus->id, 'first_name' => 'Baron', 'last_name' => 'Nava', 'status' => 'active']);
        $schedule = ScheduleTemplate::create(['campus_id' => $this->campus->id, 'days' => ['mon', 'wed'], 'starts_at' => '16:00:00', 'ends_at' => '18:00:00', 'status' => 'active']);
        $course = Course::create([
            'campus_id' => $this->campus->id,
            'academic_level_id' => AcademicLevel::create(['campus_id' => $this->campus->id, 'name' => 'HS'])->id,
            'program_id' => $level->program_id,
            'program_level_id' => $level->id,
            'teacher_id' => $teacher->id,
            'schedule_template_id' => $schedule->id,
            'name' => 'HS3A Picacho',
            'start_date' => '2026-09-07',
            'academic_hours' => 40,
            'status' => 'active',
        ]);
        $course->load(['teacher', 'period', 'scheduleTemplate', 'programLevel']);
        CoursePlanner::sync($course);
        $course->refresh()->load('managedGroup');

        $student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Gil', 'document_id' => 'V-30111222', 'status' => 'active']);
        $enrollment = Enrollment::create(['campus_id' => $this->campus->id, 'student_id' => $student->id, 'group_id' => $course->managed_group_id, 'enrolled_at' => '2026-09-07', 'status' => 'active', 'progress' => 0]);
        $repUser = User::factory()->create(['role' => 'representative', 'email' => 'marta@test.com', 'campus_id' => $this->campus->id]);
        Representative::create(['campus_id' => $this->campus->id, 'user_id' => $repUser->id, 'first_name' => 'Marta', 'last_name' => 'Gil', 'email' => 'marta@test.com', 'mobile_phone' => '0414-5556677'])
            ->students()->attach($student->id);

        return [$course, $student, $enrollment, $repUser];
    }

    public function test_templates_have_twenty_classes_per_part(): void
    {
        foreach (['preprimary', 'primary', 'hs123'] as $series) {
            foreach (['A', 'B'] as $part) {
                $lessons = LessonTemplates::lessons($series, $part);
                $this->assertCount(20, $lessons, "{$series} {$part}");
                $this->assertSame(range(1, 20), array_column($lessons, 'class_number'));
                foreach ($lessons as $lesson) {
                    $this->assertArrayHasKey($lesson['lesson_type'], LessonTemplates::TYPES);
                }
            }
        }

        $hsB = LessonTemplates::lessons('hs123', 'B');
        $this->assertSame('Review 3.1 - 3.4', $hsB[2]['unit']);
        $this->assertSame('Final speech / Meeting', $hsB[19]['unit']);
        $this->assertSame('P.E. 3-4', LessonTemplates::lessons('primary', 'B')[6]['unit']);
        $this->assertSame('Getting to know you!', LessonTemplates::lessons('preprimary', 'A')[0]['content']);
    }

    public function test_command_replaces_placeholders_keeps_manual_edits_and_is_idempotent(): void
    {
        $this->level('HS3A')->lessons()->where('class_number', 2)->update(['unit' => 'UNIT 1 VERB TO BE', 'content' => 'Contenido propio']);

        $this->artisan('program-lessons:apply-file-templates', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();
        $this->assertSame('Unidad 1', $this->level('HS3A')->lessons()->where('class_number', 1)->value('unit'));

        $this->artisan('program-lessons:apply-file-templates')
            ->expectsOutputToContain('editada a mano')
            ->expectsOutputToContain('HS4A')
            ->assertSuccessful();

        $hs3a = $this->level('HS3A')->lessons()->orderBy('class_number')->get();
        $this->assertCount(20, $hs3a);
        $this->assertSame('Unit 1.1 - 1.2', $hs3a[0]->unit);
        $this->assertSame('UNIT 1 VERB TO BE', $hs3a[1]->unit);
        $this->assertSame('review', $hs3a[2]->lesson_type);
        $this->assertSame(['vr', 'meeting'], $hs3a[8]->activity_tags);
        $this->assertSame('Unit 1A', $this->level('PRIMARY2A')->lessons()->where('class_number', 1)->value('unit'));
        $this->assertSame('Unidad 1', $this->level('HS4A')->lessons()->where('class_number', 1)->value('unit'));
        $this->assertSame(LessonTemplates::FRAMEWORKS['teens_adults'], Program::query()->where('code', 'HS')->first()->class_framework);
        $this->assertTrue(AuditLog::query()->where('action', 'program_lessons.file_templates_applied')->exists());

        $this->artisan('program-lessons:apply-file-templates')
            ->expectsOutputToContain('Sin cambios')
            ->assertSuccessful();

        $this->artisan('program-lessons:apply-file-templates', ['--overwrite' => true, '--level' => 'HS3A'])->assertSuccessful();
        $this->assertSame('Unit 1.3 - 1.4', $this->level('HS3A')->lessons()->where('class_number', 2)->value('unit'));
    }

    public function test_command_refreshes_the_plan_of_active_courses_and_attendance_shows_framework(): void
    {
        [$course] = $this->courseWithStudent();
        $first = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->first();
        $this->assertSame('Unidad 1', $first->planned_unit);

        $this->artisan('program-lessons:apply-file-templates')->assertSuccessful();

        $first->refresh();
        $this->assertSame('Unit 1.1 - 1.2', $first->planned_unit);
        $this->actingAs($this->master)->get(route('attendance.index', ['class_session_id' => $first->id]))
            ->assertOk()
            ->assertSee('Estructura de la clase')
            ->assertSee('Games or Activities')
            ->assertSee('Tipo de clase');
    }

    public function test_csv_import_shows_preview_and_then_saves(): void
    {
        $level = $this->level('PREPRIMARY1A');
        $csv = "\xEF\xBB\xBFclase;unidad;contenido;tipo\n2;Unit 1 - Me;Vocab: head, eyes, nose;Unidad\n3;Unit 1 - Me;Grammar: I am;review\nx;mal;fila;unit\n4;Unit 1;Sin tipo válido;fiesta\n";
        $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

        $this->actingAs($this->master)->post(route('program-level-lessons.import.preview', $level), ['file' => $file])
            ->assertOk()
            ->assertSee('Vocab: head, eyes, nose')
            ->assertSee('Reemplaza la clase actual')
            ->assertSee('Fila 4')
            ->assertSee('fiesta');
        $this->assertSame('Unidad 2', $level->lessons()->where('class_number', 2)->value('unit'));

        $this->actingAs($this->master)->post(route('program-level-lessons.import.store', $level))
            ->assertRedirect(route('program-levels.show', $level))
            ->assertSessionHas('success', 'Plan de clases importado: 2 clase(s).');

        $lesson = $level->lessons()->where('class_number', 2)->first();
        $this->assertSame('Vocab: head, eyes, nose', $lesson->content);
        $this->assertSame('unit', $lesson->lesson_type);
        $this->assertSame('review', $level->lessons()->where('class_number', 3)->value('lesson_type'));
        $this->assertSame(20, $level->lessons()->count());

        // La vista previa se consume al confirmar.
        $this->actingAs($this->master)->post(route('program-level-lessons.import.store', $level))
            ->assertSessionHasErrors('file');
    }

    public function test_lesson_form_and_program_framework_are_editable(): void
    {
        $lesson = $this->level('HS1A')->lessons()->where('class_number', 1)->first();

        $this->actingAs($this->master)->put(route('program-level-lessons.update', $lesson), [
            'class_number' => 1, 'sort_order' => 1, 'unit' => 'Unit 1', 'content' => 'Workshop de bienvenida',
            'lesson_type' => 'workshop', 'activity_tags' => ['vr', 'meeting'],
        ])->assertSessionHasNoErrors();
        $lesson->refresh();
        $this->assertSame('workshop', $lesson->lesson_type);
        $this->assertSame(['vr', 'meeting'], $lesson->activity_tags);
        $this->assertFalse($lesson->from_template);

        $program = Program::query()->where('code', 'PRIMARY')->first();
        $this->actingAs($this->master)->put(route('programs.update', $program), [
            'name' => $program->name, 'code' => $program->code, 'status' => 'active',
            'class_framework' => "Lead in\n\n  Song  \nClosure",
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Lead in', 'Song', 'Closure'], $program->fresh()->class_framework);
    }

    public function test_logbook_view_and_pdf_from_student_page_and_portals(): void
    {
        $this->artisan('program-lessons:apply-file-templates')->assertSuccessful();
        [$course, $student, $enrollment, $repUser] = $this->courseWithStudent();
        $sessions = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->get();
        AttendanceRecord::create(['class_session_id' => $sessions[0]->id, 'enrollment_id' => $enrollment->id, 'status' => 'present']);
        AttendanceRecord::create(['class_session_id' => $sessions[1]->id, 'enrollment_id' => $enrollment->id, 'status' => 'absent']);

        $this->actingAs($this->master)->get(route('students.show', $student))
            ->assertSee(route('students.logbook', [$student, $enrollment]), false);
        $this->actingAs($this->master)->get(route('students.logbook', [$student, $enrollment]))
            ->assertOk()
            ->assertSee('High School 1-2-3 File A')
            ->assertSee('Unit 1.1 - 1.2')
            ->assertSee('Ausente');
        $this->actingAs($this->master)->get(route('students.logbook.pdf', [$student, $enrollment]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($repUser)->get(route('portal.representative'))
            ->assertSee(route('portal.representative.logbook.pdf', $enrollment), false);
        $this->actingAs($repUser)->get(route('portal.representative.logbook.pdf', $enrollment))->assertOk();

        $otherRep = User::factory()->create(['role' => 'representative', 'email' => 'otro@test.com', 'campus_id' => $this->campus->id]);
        Representative::create(['campus_id' => $this->campus->id, 'user_id' => $otherRep->id, 'first_name' => 'Otro', 'last_name' => 'Rep', 'email' => 'otro@test.com']);
        $this->actingAs($otherRep)->get(route('portal.representative.logbook.pdf', $enrollment))->assertForbidden();

        $studentUser = User::factory()->create(['role' => 'student', 'email' => 'ana@test.com', 'campus_id' => $this->campus->id]);
        $student->update(['user_id' => $studentUser->id]);
        $this->actingAs($studentUser)->get(route('portal.student.logbook.pdf', $enrollment))->assertOk();
    }
}
