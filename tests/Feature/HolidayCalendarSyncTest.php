<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Period;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Role;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CoursePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_holiday_moves_session_off_that_date_and_extends_end_date(): void
    {
        [$admin, $course] = $this->fridayCourse();
        $originalEnd = $course->end_date->toDateString();
        $count = $this->sessionCount($course);

        $this->actingAs($admin)
            ->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'))
            ->assertRedirect(route('holidays.index'))
            ->assertSessionHas('success');

        $course->refresh();
        $this->assertFalse($this->hasSessionOn($course, '2026-06-19'));
        $this->assertSame($count, $this->sessionCount($course));
        $this->assertSame('2026-07-31', $originalEnd);
        $this->assertSame('2026-08-07', $course->end_date->toDateString());
    }

    public function test_session_with_attendance_on_holiday_is_kept_and_reported(): void
    {
        [$admin, $course, $enrollment] = $this->fridayCourse();
        $count = $this->sessionCount($course);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();
        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $this->actingAs($admin)
            ->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'))
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, '19/06/2026'));

        $this->assertTrue($this->hasSessionOn($course, '2026-06-19'));
        $this->assertSame(1, AttendanceRecord::query()->where('class_session_id', $session->id)->count());
        // Se mantiene y se agrega la clase que falta al final (la fecha de fin no se adelanta).
        $this->assertSame($count + 1, $this->sessionCount($course));
        $this->assertSame('2026-08-07', $course->fresh()->end_date->toDateString());

        $this->actingAs($admin)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Feriado')
            ->assertSee('Recalcular calendario')
            ->assertSee('Editar fecha');
    }

    public function test_moving_conflicting_session_then_recalculating_leaves_no_extra_session(): void
    {
        [$admin, $course, $enrollment] = $this->fridayCourse();
        $originalCount = $this->sessionCount($course);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();
        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $this->actingAs($admin)->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'));
        $this->assertSame($originalCount + 1, $this->sessionCount($course));

        $this->actingAs($admin)
            ->put(route('sessions.update', $session), $this->sessionPayload($session, '2026-06-26'))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('courses.recalculate-calendar', $course->fresh()));

        $this->assertSame($originalCount, $this->sessionCount($course));
        $this->assertFalse($this->hasSessionOn($course, '2026-06-19'));
        $onDate = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-26')->get();
        $this->assertCount(1, $onDate);
        $this->assertSame($session->id, $onDate->first()->id);
        $this->assertSame(1, AttendanceRecord::query()->where('class_session_id', $session->id)->count());
    }

    public function test_deleting_holiday_resyncs_courses(): void
    {
        [$admin, $course] = $this->fridayCourse();
        $this->actingAs($admin)->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'));
        $this->assertFalse($this->hasSessionOn($course, '2026-06-19'));

        $holiday = Holiday::query()->firstOrFail();
        $this->actingAs($admin)->delete(route('holidays.destroy', $holiday))->assertRedirect();

        $this->assertTrue($this->hasSessionOn($course, '2026-06-19'));
        $this->assertSame('2026-07-31', $course->fresh()->end_date->toDateString());
    }

    public function test_recalculate_button_applies_existing_holidays(): void
    {
        [$admin, $course] = $this->fridayCourse();
        Holiday::query()->create([
            'campus_id' => $course->campus_id,
            'name' => 'Vacaciones',
            'holiday_date' => '2026-06-26',
            'is_recurring' => false,
            'status' => 'active',
        ]);
        $this->assertTrue($this->hasSessionOn($course, '2026-06-26'));

        $this->actingAs($admin)
            ->post(route('courses.recalculate-calendar', $course))
            ->assertRedirect(route('courses.show', $course))
            ->assertSessionHas('success');

        $this->assertFalse($this->hasSessionOn($course, '2026-06-26'));
    }

    public function test_teacher_topic_is_preserved_on_resync(): void
    {
        [$admin, $course] = $this->fridayCourse();
        ClassSession::query()
            ->where('group_id', $course->managed_group_id)
            ->whereDate('session_date', '2026-06-12')
            ->update(['topic' => 'Unit 2 vocabulary', 'program_status' => 'on_track']);

        $this->actingAs($admin)->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'));

        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-12')->firstOrFail();
        $this->assertSame('Unit 2 vocabulary', $session->topic);
        $this->assertSame('on_track', $session->program_status);
    }

    public function test_session_can_be_moved_after_group_end_date_and_end_date_updates(): void
    {
        [$admin, $course] = $this->fridayCourse();
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('sessions.update', $session), $this->sessionPayload($session, '2026-08-14') + ['redirect_to' => 'course'])
            ->assertRedirect(route('courses.show', $course))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-14', $session->fresh()->session_date->toDateString());
        $this->assertSame('2026-08-14', $course->fresh()->end_date->toDateString());
        $this->assertSame('2026-08-14', $course->fresh()->managedGroup->end_date->toDateString());
    }

    public function test_session_cannot_be_moved_to_a_holiday(): void
    {
        [$admin, $course] = $this->fridayCourse();
        Holiday::query()->create([
            'campus_id' => null,
            'name' => 'Día patrio',
            'holiday_date' => '2026-08-14',
            'is_recurring' => false,
            'status' => 'active',
        ]);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('sessions.update', $session), $this->sessionPayload($session, '2026-08-14'))
            ->assertSessionHasErrors('session_date');

        $this->assertSame('2026-06-05', $session->fresh()->session_date->toDateString());
    }

    public function test_session_edit_accepts_times_with_seconds(): void
    {
        [$admin, $course] = $this->fridayCourse();
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('sessions.update', $session), [
                'group_id' => $session->group_id,
                'session_date' => '2026-06-05',
                'starts_at' => '14:20:00',
                'ends_at' => '17:40:00',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('14:20', substr((string) $session->fresh()->starts_at, 0, 5));
    }

    public function test_manually_moved_session_survives_recalculation(): void
    {
        [$admin, $course] = $this->fridayCourse();
        $count = $this->sessionCount($course);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-12')->firstOrFail();

        // Se da el sábado en lugar del viernes (fuera del horario del curso).
        $this->actingAs($admin)
            ->put(route('sessions.update', $session), $this->sessionPayload($session, '2026-06-13'))
            ->assertSessionHasNoErrors();
        $this->assertTrue($session->fresh()->date_locked);
        $this->assertSame('2026-06-12', $session->fresh()->rescheduled_from->toDateString());

        $this->actingAs($admin)->post(route('courses.recalculate-calendar', $course->fresh()))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('holidays.store'), $this->holidayPayload($course, '2026-07-03'));

        $this->assertSame('2026-06-13', $session->fresh()->session_date->toDateString());
        $this->assertFalse($this->hasSessionOn($course, '2026-06-12'), 'No debe regenerarse la clase en la fecha original.');
        $this->assertSame($count, $this->sessionCount($course));
        $sequences = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->pluck('sequence')->all();
        $this->assertSame(range(1, $count), $sequences);
    }

    public function test_teacher_can_move_own_session_from_attendance_screen(): void
    {
        [, $course] = $this->fridayCourse();
        $teacherUser = $this->teacherUserFor($course);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();

        $this->actingAs($teacherUser)
            ->get(route('attendance.index', ['class_session_id' => $session->id]))
            ->assertOk()
            ->assertSee('Cambiar fecha');

        $this->actingAs($teacherUser)
            ->post(route('attendance.reschedule'), ['class_session_id' => $session->id, 'session_date' => '2026-06-20'])
            ->assertRedirect(route('attendance.index', ['class_session_id' => $session->id]))
            ->assertSessionHas('success');

        $this->assertSame('2026-06-20', $session->fresh()->session_date->toDateString());
        $this->assertTrue($session->fresh()->date_locked);
    }

    public function test_teacher_cannot_move_another_teachers_session(): void
    {
        [, $course] = $this->fridayCourse();
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->firstOrFail();

        $otherTeacher = Teacher::query()->create([
            'campus_id' => $course->campus_id,
            'first_name' => 'Otro',
            'last_name' => 'Docente',
            'email' => 'otro-docente@test.dev',
            'status' => 'active',
        ]);
        $otherUser = $this->makeTeacherUser($otherTeacher);

        $this->actingAs($otherUser)
            ->post(route('attendance.reschedule'), ['class_session_id' => $session->id, 'session_date' => '2026-06-06'])
            ->assertForbidden();

        $this->assertSame('2026-06-05', $session->fresh()->session_date->toDateString());
    }

    public function test_attendance_reschedule_rejects_holiday(): void
    {
        [$admin, $course] = $this->fridayCourse();
        Holiday::query()->create(['campus_id' => null, 'name' => 'Carnaval', 'holiday_date' => '2026-06-20', 'is_recurring' => false, 'status' => 'active']);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('attendance.reschedule'), ['class_session_id' => $session->id, 'session_date' => '2026-06-20'])
            ->assertSessionHasErrors('session_date');

        $this->assertSame('2026-06-19', $session->fresh()->session_date->toDateString());
    }

    public function test_course_starting_on_a_holiday_with_attendance_can_be_recalculated(): void
    {
        [$admin, $course, $enrollment] = $this->fridayCourse();
        // El inicio del curso (05/06) pasa a ser feriado y ya hay asistencia en una clase posterior.
        $this->actingAs($admin)->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-05'));
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();
        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $this->actingAs($admin)
            ->post(route('holidays.store'), $this->holidayPayload($course, '2026-07-03'))
            ->assertSessionMissing('info');

        $this->assertFalse($this->hasSessionOn($course, '2026-07-03'));
        $this->assertFalse($this->hasSessionOn($course, '2026-06-05'));
    }

    public function test_moving_onto_an_occupied_date_replaces_the_planned_class(): void
    {
        [$admin, $course, $enrollment] = $this->fridayCourse();
        $count = $this->sessionCount($course);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();
        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);
        $this->actingAs($admin)->post(route('holidays.store'), $this->holidayPayload($course, '2026-06-19'));

        // Mismo grupo, misma fecha y misma hora que la clase planificada del 26/06 (índice único en BD).
        $this->actingAs($admin)
            ->put(route('sessions.update', $session), $this->sessionPayload($session, '2026-06-26'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $onDate = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-26')->get();
        $this->assertCount(1, $onDate);
        $this->assertSame($session->id, $onDate->first()->id);
        $this->assertSame($count, $this->sessionCount($course));
        $this->assertSame(1, AttendanceRecord::query()->where('class_session_id', $session->id)->count());
    }

    public function test_attendance_reschedule_onto_occupied_date_works_for_teacher(): void
    {
        [, $course] = $this->fridayCourse();
        $teacherUser = $this->teacherUserFor($course);
        $count = $this->sessionCount($course);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();

        $this->actingAs($teacherUser)
            ->post(route('attendance.reschedule'), ['class_session_id' => $session->id, 'session_date' => '2026-06-26'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('2026-06-26', $session->fresh()->session_date->toDateString());
        $this->assertSame(1, ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-26')->count());
        $this->assertFalse($this->hasSessionOn($course, '2026-06-19'));
        $this->assertSame($count, $this->sessionCount($course));
    }

    public function test_moving_onto_a_class_with_attendance_is_rejected_without_error_500(): void
    {
        [$admin, $course, $enrollment] = $this->fridayCourse();
        $target = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-26')->firstOrFail();
        AttendanceRecord::query()->create([
            'class_session_id' => $target->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('attendance.reschedule'), ['class_session_id' => $session->id, 'session_date' => '2026-06-26'])
            ->assertRedirect()
            ->assertSessionHasErrors('session_date');

        $this->actingAs($admin)
            ->put(route('sessions.update', $session), $this->sessionPayload($session, '2026-06-26'))
            ->assertSessionHasErrors('session_date');

        $this->assertSame('2026-06-19', $session->fresh()->session_date->toDateString());
        $this->assertNotNull($target->fresh());
    }

    public function test_cascade_moves_holiday_class_onto_attended_class_and_pushes_the_rest(): void
    {
        [, $course, $enrollment] = $this->fridayCourse();
        $teacherUser = $this->teacherUserFor($course);
        $count = $this->sessionCount($course);
        $holidayClass = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();
        $nextClass = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-26')->firstOrFail();
        foreach ([$holidayClass, $nextClass] as $session) {
            AttendanceRecord::query()->create([
                'class_session_id' => $session->id,
                'enrollment_id' => $enrollment->id,
                'status' => $session->is($holidayClass) ? AttendanceRecord::STATUS_PRESENT : AttendanceRecord::STATUS_ABSENT,
            ]);
        }
        $nextClass->update(['topic' => 'Unit 3']);
        Holiday::query()->create(['campus_id' => null, 'name' => 'Feriado', 'holiday_date' => '2026-06-19', 'is_recurring' => false, 'status' => 'active']);
        $this->actingAs($teacherUser)->post(route('courses.recalculate-calendar', $course))->assertForbidden();
        CoursePlanner::sync($course->fresh(), true);
        $this->assertSame($count + 1, $this->sessionCount($course));

        // Sin cascada se rechaza (el 26/06 ya tiene asistencia).
        $this->actingAs($teacherUser)
            ->post(route('attendance.reschedule'), ['class_session_id' => $holidayClass->id, 'session_date' => '2026-06-26'])
            ->assertSessionHasErrors('session_date');

        // Con cascada: la del feriado pasa al 26/06 y la del 26/06 al 03/07, cada una con su asistencia.
        $this->actingAs($teacherUser)
            ->post(route('attendance.reschedule'), ['class_session_id' => $holidayClass->id, 'session_date' => '2026-06-26', 'cascade' => 1])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Se corrieron'));

        $this->assertSame('2026-06-26', $holidayClass->fresh()->session_date->toDateString());
        $this->assertSame('2026-07-03', $nextClass->fresh()->session_date->toDateString());
        $this->assertSame(AttendanceRecord::STATUS_PRESENT, AttendanceRecord::query()->where('class_session_id', $holidayClass->id)->value('status'));
        $this->assertSame(AttendanceRecord::STATUS_ABSENT, AttendanceRecord::query()->where('class_session_id', $nextClass->id)->value('status'));
        $this->assertSame('Unit 3', $nextClass->fresh()->topic);

        // La clase extra que compensaba el feriado ya no hace falta: vuelve al total y a la fecha de fin originales.
        $this->assertSame($count, $this->sessionCount($course));
        $this->assertSame('2026-08-07', $course->fresh()->end_date->toDateString());
        $dates = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->pluck('session_date')->map->toDateString()->all();
        $this->assertSame(['2026-06-05', '2026-06-12', '2026-06-26', '2026-07-03', '2026-07-10', '2026-07-17', '2026-07-24', '2026-07-31', '2026-08-07'], $dates);
        $this->assertSame(range(1, $count), ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->pluck('sequence')->all());

        // Estable: recalcular de nuevo no cambia nada.
        CoursePlanner::sync($course->fresh(), true);
        $this->assertSame($dates, ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->pluck('session_date')->map->toDateString()->all());
    }

    public function test_cascade_skips_holidays_when_pushing(): void
    {
        [$admin, $course, $enrollment] = $this->fridayCourse();
        $first = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-12')->firstOrFail();
        $second = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();
        AttendanceRecord::query()->create(['class_session_id' => $second->id, 'enrollment_id' => $enrollment->id, 'status' => AttendanceRecord::STATUS_PRESENT]);
        // El 26/06 pasa a ser feriado sin recalcular todavía: la cascada no debe caer ahí.
        Holiday::query()->create(['campus_id' => null, 'name' => 'Feriado', 'holiday_date' => '2026-06-26', 'is_recurring' => false, 'status' => 'active']);
        ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-26')->delete();

        $this->actingAs($admin)
            ->post(route('attendance.reschedule'), ['class_session_id' => $first->id, 'session_date' => '2026-06-19', 'cascade' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-06-19', $first->fresh()->session_date->toDateString());
        $this->assertSame('2026-07-03', $second->fresh()->session_date->toDateString());
        $this->assertFalse($this->hasSessionOn($course, '2026-06-26'));
    }

    public function test_move_in_course_whose_classes_start_before_its_start_date_keeps_all_classes(): void
    {
        // Caso Primary 3A: el curso dice que empieza una semana después de su primera clase.
        [$admin, $course, $enrollment] = $this->fridayCourse();
        $count = $this->sessionCount($course);
        Course::query()->whereKey($course->id)->update(['start_date' => '2026-06-12']);
        $attended = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();
        AttendanceRecord::query()->create(['class_session_id' => $attended->id, 'enrollment_id' => $enrollment->id, 'status' => AttendanceRecord::STATUS_PRESENT]);

        // Se mueve la clase con asistencia sobre la clase planificada del 26/06 (que se reemplaza).
        $this->actingAs($admin)
            ->put(route('sessions.update', $attended), $this->sessionPayload($attended, '2026-06-26'))
            ->assertSessionHasNoErrors();

        $sequences = ClassSession::query()->where('group_id', $course->managed_group_id)->orderBy('session_date')->pluck('sequence')->all();
        $this->assertSame($count, $this->sessionCount($course), 'No debe perderse ninguna clase.');
        $this->assertSame(range(1, $count), $sequences, 'La numeración no debe tener saltos.');
        $this->assertSame('2026-06-26', $attended->fresh()->session_date->toDateString());
    }

    public function test_move_is_rolled_back_when_course_cannot_be_recalculated(): void
    {
        [$admin, $course, $enrollment] = $this->fridayCourse();
        $count = $this->sessionCount($course);
        $first = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-05')->firstOrFail();
        AttendanceRecord::query()->create(['class_session_id' => $first->id, 'enrollment_id' => $enrollment->id, 'status' => AttendanceRecord::STATUS_PRESENT]);
        // Asistencia antes de la fecha de inicio: el curso no se puede recalcular.
        Course::query()->whereKey($course->id)->update(['start_date' => '2026-06-12']);
        $session = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-19')->firstOrFail();
        $planned = ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', '2026-06-26')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('attendance.reschedule'), ['class_session_id' => $session->id, 'session_date' => '2026-06-26'])
            ->assertSessionHasErrors('session_date');

        $this->assertSame('2026-06-19', $session->fresh()->session_date->toDateString());
        $this->assertFalse((bool) $session->fresh()->date_locked);
        $this->assertNotNull($planned->fresh(), 'La clase planificada no debe borrarse si el movimiento no se completa.');
        $this->assertSame($count, $this->sessionCount($course));
    }

    private function teacherUserFor(Course $course): User
    {
        return $this->makeTeacherUser(Teacher::query()->findOrFail($course->teacher_id));
    }

    private function makeTeacherUser(Teacher $teacher): User
    {
        $user = User::factory()->create([
            'campus_id' => $teacher->campus_id,
            'role' => 'teacher',
            'email' => $teacher->email,
        ]);
        $role = Role::query()->firstOrCreate(['name' => 'teacher'], ['label' => 'Profesor']);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $teacher->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function holidayPayload(Course $course, string $date): array
    {
        return [
            'campus_id' => $course->campus_id,
            'name' => 'Vacaciones',
            'is_recurring' => 0,
            'holiday_date' => $date,
            'status' => 'active',
        ];
    }

    private function sessionPayload(ClassSession $session, string $date): array
    {
        return [
            'group_id' => $session->group_id,
            'session_date' => $date,
            'starts_at' => substr((string) $session->starts_at, 0, 5),
            'ends_at' => substr((string) $session->ends_at, 0, 5),
        ];
    }

    private function sessionCount(Course $course): int
    {
        return ClassSession::query()->where('group_id', $course->managed_group_id)->count();
    }

    private function hasSessionOn(Course $course, string $date): bool
    {
        return ClassSession::query()->where('group_id', $course->managed_group_id)->whereDate('session_date', $date)->exists();
    }

    /**
     * Curso de viernes que arranca el 05/06/2026: 9 sesiones, termina el 31/07/2026.
     *
     * @return array{0: User, 1: Course, 2: Enrollment}
     */
    private function fridayCourse(): array
    {
        $campus = Campus::query()->create(['name' => 'La Cascada', 'code' => 'CAS', 'status' => 'active']);
        $admin = User::factory()->create(['campus_id' => $campus->id, 'role' => 'admin', 'is_master' => true]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $teacher = Teacher::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Teacher',
            'last_name' => 'Holiday',
            'email' => 'teacher-holiday@test.dev',
            'status' => 'active',
        ]);

        $program = Program::query()->firstOrFail();
        $programLevel = ProgramLevel::query()->where('program_id', $program->id)->orderBy('sort_order')->firstOrFail();
        $academicLevel = AcademicLevel::query()->create(['campus_id' => $campus->id, 'name' => 'HS']);
        $period = Period::query()->create([
            'campus_id' => $campus->id,
            'code' => '2026-Q2',
            'description' => 'Q2',
            'status' => 'active',
        ]);
        $schedule = ScheduleTemplate::query()->create([
            'campus_id' => $campus->id,
            'days' => ['fri'],
            'starts_at' => '14:20:00',
            'ends_at' => '17:40:00',
            'status' => 'active',
        ]);

        $course = Course::query()->create([
            'campus_id' => $campus->id,
            'academic_level_id' => $academicLevel->id,
            'program_id' => $program->id,
            'program_level_id' => $programLevel->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'schedule_template_id' => $schedule->id,
            'name' => 'HS Friday',
            'start_date' => '2026-06-05',
            'academic_hours' => 40,
            'status' => 'active',
        ]);
        $course->load(['teacher', 'period', 'scheduleTemplate', 'programLevel']);
        CoursePlanner::sync($course);

        $student = Student::query()->create([
            'campus_id' => $campus->id,
            'first_name' => 'Student',
            'last_name' => 'Holiday',
            'status' => 'active',
        ]);
        $enrollment = Enrollment::query()->create([
            'campus_id' => $campus->id,
            'student_id' => $student->id,
            'group_id' => $course->fresh()->managed_group_id,
            'enrolled_at' => '2026-06-05',
            'status' => 'active',
            'progress' => 0,
        ]);

        return [$admin, $course->fresh(), $enrollment];
    }
}
