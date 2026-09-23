<?php

namespace App\Support;

use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Group;
use App\Models\Holiday;
use App\Models\ProgramLevelLesson;
use App\Models\ScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CoursePlanner
{
    public const ACADEMIC_HOUR_MINUTES = 45;

    public static function sync(Course $course, bool $regenerateSessions = true): Course
    {
        if (! $course->schedule_template_id || ! $course->start_date || ! $course->academic_hours) {
            if ($regenerateSessions) {
                return $course;
            }

            $group = self::syncManagedGroup($course);
            self::persistManagedGroupId($course, $group);

            return $course->fresh(['teacher', 'period', 'scheduleTemplate', 'managedGroup']);
        }

        $schedule = $course->scheduleTemplate;
        if (! $schedule) {
            throw ValidationException::withMessages([
                'schedule_template_id' => 'El horario seleccionado no existe.',
            ]);
        }

        if (! $regenerateSessions) {
            $group = self::syncManagedGroup($course);
            self::persistManagedGroupId($course, $group);

            return $course->fresh(['teacher', 'period', 'scheduleTemplate', 'managedGroup']);
        }

        $slotMinutes = self::slotMinutes($schedule);
        if ($slotMinutes <= 0) {
            throw ValidationException::withMessages([
                'schedule_template_id' => 'El horario debe tener una duración válida.',
            ]);
        }

        $requiredSessions = (int) ceil(($course->academic_hours * self::ACADEMIC_HOUR_MINUTES) / $slotMinutes);
        if ($requiredSessions <= 0) {
            throw ValidationException::withMessages([
                'academic_hours' => 'La duración del curso debe ser mayor a cero.',
            ]);
        }

        $group = self::syncManagedGroup($course);
        $endDate = self::syncSessions($course, $group, $schedule, $requiredSessions);

        $group->forceFill([
            'end_date' => $endDate?->toDateString(),
        ])->save();

        $course->forceFill([
            'managed_group_id' => $group->id,
            'end_date' => $endDate?->toDateString(),
        ])->save();

        return $course->fresh(['teacher', 'period', 'scheduleTemplate', 'managedGroup']);
    }

    private static function syncManagedGroup(Course $course): Group
    {
        $group = $course->managedGroup
            ?: Group::where('course_id', $course->id)->orderBy('id')->first()
            ?: new Group();

        $group->fill([
            'campus_id' => $course->campus_id,
            'course_id' => $course->id,
            'teacher_id' => $course->teacher_id,
            'name' => $course->code ?: $course->name,
            'period' => $course->period?->code,
            'schedule' => $course->scheduleTemplate?->display_label,
            'start_date' => $course->start_date,
            'end_date' => $course->end_date,
            'status' => $course->status === 'inactive' ? 'inactive' : 'active',
        ]);

        if (! $group->capacity) {
            $group->capacity = 30;
        }

        $group->save();

        return $group;
    }

    private static function persistManagedGroupId(Course $course, Group $group): void
    {
        if ($course->managed_group_id === $group->id) {
            return;
        }

        $course->forceFill(['managed_group_id' => $group->id])->saveQuietly();
    }

    private static function syncSessions(Course $course, Group $group, ScheduleTemplate $schedule, int $requiredSessions): ?Carbon
    {
        $existingSessions = $group->sessions()
            ->withCount(['attendanceRecords', 'makeupRequests'])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get();

        $protectedSessions = $existingSessions->filter(fn (ClassSession $session) => self::sessionIsProtected($session));

        if ($protectedSessions->isNotEmpty()) {
            $holidays = self::holidaysFor($course);
            $excludedDates = self::excludedDates($existingSessions);
            $firstScheduled = self::buildScheduleDates($course->start_date, $schedule, 1, $holidays, $excludedDates)->first();

            // Se ignoran las clases movidas a mano y las que caen en feriado/fecha excluida: no definen el inicio del curso.
            $firstRegular = $existingSessions->first(fn (ClassSession $session) => ! $session->date_locked
                && $session->session_date
                && ! self::isHoliday($session->session_date, $holidays)
                && ! in_array($session->session_date->toDateString(), $excludedDates, true));

            if ($firstRegular && $firstScheduled && $firstRegular->session_date->toDateString() !== $firstScheduled->toDateString()) {
                throw ValidationException::withMessages([
                    'start_date' => sprintf(
                        'La primera clase del curso (%s) no coincide con su fecha de inicio (%s) y ya hay asistencia registrada. Ajusta la fecha de inicio en "Editar curso" para que coincida con la primera clase real.',
                        $firstRegular->session_date->format('d/m/Y'),
                        $firstScheduled->format('d/m/Y'),
                    ),
                ]);
            }
        }

        if ($existingSessions->isEmpty()) {
            return self::insertFreshSessions($course, $group, $schedule, $requiredSessions);
        }

        return self::mergeSessions($course, $group, $schedule, $requiredSessions, $existingSessions);
    }

    public static function holidaysFor(Course $course): Collection
    {
        return Holiday::query()
            ->active()
            ->forCampus($course->campus_id)
            ->get();
    }

    /**
     * Fechas de las que se movieron clases a mano: el horario no debe volver a usarlas.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, string>
     */
    public static function excludedDates(Collection $sessions): array
    {
        return $sessions
            ->filter(fn (ClassSession $session) => $session->date_locked && $session->rescheduled_from)
            ->map(fn (ClassSession $session) => $session->rescheduled_from->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Primer día del horario del curso posterior a $after que no sea feriado ni fecha excluida.
     *
     * @param  array<int, string>  $excludedDates
     */
    public static function scheduleDateAfter(Course $course, Carbon $after, Collection $holidays, array $excludedDates = []): ?Carbon
    {
        $schedule = $course->scheduleTemplate;
        if (! $schedule) {
            return null;
        }

        return self::buildScheduleDates($after->copy()->startOfDay()->addDay(), $schedule, 1, $holidays, $excludedDates)->first();
    }

    /**
     * Sesiones del curso que caen en un feriado activo de su sede.
     *
     * @return Collection<int, ClassSession>
     */
    public static function sessionsOnHolidays(Course $course, bool $onlyProtected = false): Collection
    {
        $groupId = $course->managed_group_id;
        if (! $groupId) {
            return collect();
        }

        $holidays = Holiday::query()->active()->forCampus($course->campus_id)->get();
        if ($holidays->isEmpty()) {
            return collect();
        }

        return ClassSession::query()
            ->where('group_id', $groupId)
            ->withCount(['attendanceRecords', 'makeupRequests'])
            ->orderBy('session_date')
            ->get()
            ->filter(fn (ClassSession $session) => $session->session_date
                && self::isHoliday($session->session_date, $holidays)
                && (! $onlyProtected || self::sessionIsProtected($session)))
            ->values();
    }

    private static function sessionIsProtected(ClassSession $session): bool
    {
        return (bool) $session->date_locked
            || ($session->attendance_records_count ?? 0) > 0
            || ($session->makeup_requests_count ?? 0) > 0;
    }

    private static function insertFreshSessions(
        Course $course,
        Group $group,
        ScheduleTemplate $schedule,
        int $requiredSessions,
    ): ?Carbon {
        $holidays = Holiday::query()
            ->active()
            ->forCampus($course->campus_id)
            ->get();

        $dates = self::buildScheduleDates($course->start_date, $schedule, $requiredSessions, $holidays);
        $plannedLessons = $course->programLevel?->lessons()->orderBy('sort_order')->get() ?? collect();
        $distribution = self::distributeLessons($plannedLessons, $requiredSessions);
        $payload = [];

        foreach ($dates as $index => $date) {
            $payload[] = self::sessionAttributes(
                $course,
                $group,
                $schedule,
                $date,
                $index + 1,
                $distribution[$index] ?? collect(),
            );
        }

        ClassSession::insert($payload);

        return collect($dates)->last();
    }

    /**
     * @param  Collection<int, ClassSession>  $existingSessions
     */
    private static function mergeSessions(
        Course $course,
        Group $group,
        ScheduleTemplate $schedule,
        int $requiredSessions,
        Collection $existingSessions,
    ): ?Carbon {
        $holidays = self::holidaysFor($course);
        $excludedDates = self::excludedDates($existingSessions);
        $locked = $existingSessions->filter(fn (ClassSession $session) => (bool) $session->date_locked);

        // Las clases movidas a mano fuera del horario ocupan el lugar de la clase original: el horario
        // genera solo las que faltan. Las que tienen asistencia en un feriado NO cuentan (se agrega la
        // clase al final), para no adelantar la fecha de fin ni disparar renovaciones antes de tiempo.
        $slots = $requiredSessions;
        for ($guard = 0; $guard <= $requiredSessions; $guard++) {
            $dates = self::buildScheduleDates($course->start_date, $schedule, $slots, $holidays, $excludedDates);
            $dateKeys = $dates->map(fn (Carbon $date) => $date->toDateString())->flip();
            $offSchedule = $locked
                ->groupBy(fn (ClassSession $session) => $session->session_date?->toDateString() ?? '')
                ->map(fn (Collection $sameDay, $dateKey) => $dateKeys->has((string) $dateKey) ? $sameDay->count() - 1 : $sameDay->count())
                ->sum();
            $nextSlots = max(0, $requiredSessions - $offSchedule);
            if ($nextSlots === $slots) {
                break;
            }
            $slots = $nextSlots;
        }

        $availableByDate = $existingSessions
            ->groupBy(fn (ClassSession $session) => $session->session_date?->toDateString() ?? '');

        // 1) Decidir qué sesión existente ocupa cada fecha (si dos comparten fecha, gana la protegida).
        $usedSessionIds = [];
        $plan = [];
        foreach ($dates as $index => $date) {
            $candidate = ($availableByDate->get($date->toDateString()) ?? collect())
                ->reject(fn (ClassSession $session) => in_array($session->id, $usedSessionIds, true))
                ->sortByDesc(fn (ClassSession $session) => self::sessionIsProtected($session) ? 1 : 0)
                ->first();
            if ($candidate) {
                $usedSessionIds[] = $candidate->id;
            }
            $plan[] = [$index, $date, $candidate];
        }

        // 2) Borrar primero las sobrantes sin protección, para no chocar con el índice único
        //    (grupo, fecha, hora) al actualizar o crear las demás.
        foreach ($existingSessions as $session) {
            if (! in_array($session->id, $usedSessionIds, true) && ! self::sessionIsProtected($session)) {
                $session->delete();
            }
        }

        // 3) Aplicar el plan.
        foreach ($plan as [$index, $date, $candidate]) {
            $attributes = self::sessionAttributes(
                $course,
                $group,
                $schedule,
                $date,
                $index + 1,
                collect(),
                includeTimestamps: false,
            );

            if ($candidate) {
                $update = $attributes;
                unset($update['topic'], $update['program_status'], $update['program_notes']);
                if ($candidate->date_locked) {
                    unset($update['starts_at'], $update['ends_at']);
                }
                $candidate->update($update);

                continue;
            }

            ClassSession::create($attributes);
        }

        foreach ($existingSessions as $session) {
            $sharesDate = ($availableByDate->get($session->session_date?->toDateString() ?? '') ?? collect())->count() > 1;
            if (! in_array($session->id, $usedSessionIds, true) && self::sessionIsProtected($session) && ! $session->date_locked && ! $sharesDate) {
                $session->update([
                    'starts_at' => $schedule->starts_at,
                    'ends_at' => $schedule->ends_at,
                ]);
            }
        }

        return self::resequence($course, $group, $requiredSessions);
    }

    /**
     * Numera las clases en orden cronológico y reparte el programa planificado sobre ese orden.
     */
    private static function resequence(Course $course, Group $group, int $requiredSessions): ?Carbon
    {
        $sessions = ClassSession::query()
            ->where('group_id', $group->id)
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $plannedLessons = $course->programLevel?->lessons()->orderBy('sort_order')->get() ?? collect();
        $distribution = self::distributeLessons($plannedLessons, max($requiredSessions, $sessions->count()));

        foreach ($sessions as $index => $session) {
            $lessons = $distribution[$index] ?? collect();
            $primary = $lessons->first();
            $session->fill([
                'sequence' => $index + 1,
                'program_level_lesson_id' => $primary?->id,
                'planned_class_number' => $primary?->class_number,
                'planned_class_label' => self::lessonLabel($lessons),
                'planned_unit' => self::lessonUnit($lessons),
                'planned_content' => self::lessonContent($lessons),
            ]);
            if ($session->isDirty()) {
                $session->save();
            }
        }

        return $sessions->last()?->session_date?->copy();
    }

    /**
     * @param  Collection<int, ProgramLevelLesson>  $assignedLessons
     * @return array<string, mixed>
     */
    private static function sessionAttributes(
        Course $course,
        Group $group,
        ScheduleTemplate $schedule,
        Carbon $date,
        int $sequence,
        Collection $assignedLessons,
        bool $includeTimestamps = true,
    ): array {
        $primaryLesson = $assignedLessons->first();
        $attributes = [
            'campus_id' => $group->campus_id,
            'group_id' => $group->id,
            'program_level_lesson_id' => $primaryLesson?->id,
            'planned_class_number' => $primaryLesson?->class_number,
            'planned_class_label' => self::lessonLabel($assignedLessons),
            'planned_unit' => self::lessonUnit($assignedLessons),
            'planned_content' => self::lessonContent($assignedLessons),
            'sequence' => $sequence,
            'session_date' => $date->toDateString(),
            'starts_at' => $schedule->starts_at,
            'ends_at' => $schedule->ends_at,
            'topic' => null,
            'program_status' => null,
            'program_notes' => null,
        ];

        if ($includeTimestamps) {
            $attributes['created_at'] = now();
            $attributes['updated_at'] = now();
        }

        return $attributes;
    }

    private static function slotMinutes(ScheduleTemplate $schedule): int
    {
        $start = Carbon::createFromFormat('H:i:s', self::normalizeTime($schedule->starts_at));
        $end = Carbon::createFromFormat('H:i:s', self::normalizeTime($schedule->ends_at));

        return (int) $start->diffInMinutes($end, false);
    }

    /**
     * @param  array<int, string>  $excludedDates  Fechas Y-m-d que no deben usarse (clases movidas a mano).
     */
    private static function buildScheduleDates(Carbon $startDate, ScheduleTemplate $schedule, int $requiredSessions, Collection $holidays, array $excludedDates = []): Collection
    {
        $isoWeekdays = collect($schedule->days ?? [])
            ->map(fn (string $day) => match ($day) {
                'mon' => 1,
                'tue' => 2,
                'wed' => 3,
                'thu' => 4,
                'fri' => 5,
                'sat' => 6,
                'sun' => 7,
                default => null,
            })
            ->filter()
            ->values();

        if ($isoWeekdays->isEmpty()) {
            throw ValidationException::withMessages([
                'schedule_template_id' => 'El horario debe tener al menos un día de la semana.',
            ]);
        }

        $dates = collect();
        $cursor = $startDate->copy()->startOfDay();

        while ($dates->count() < $requiredSessions) {
            if (
                $isoWeekdays->contains($cursor->dayOfWeekIso)
                && $cursor->greaterThanOrEqualTo($startDate->copy()->startOfDay())
                && ! self::isHoliday($cursor, $holidays)
                && ! in_array($cursor->toDateString(), $excludedDates, true)
            ) {
                $dates->push($cursor->copy());
            }
            $cursor->addDay();
        }

        return $dates;
    }

    public static function normalizeTime(?string $time): string
    {
        if (! $time) {
            return '00:00:00';
        }

        return strlen($time) === 5 ? $time.':00' : $time;
    }

    private static function isHoliday(Carbon $date, Collection $holidays): bool
    {
        return $holidays->contains(fn (Holiday $holiday) => $holiday->occursOn($date));
    }

    private static function distributeLessons(Collection $lessons, int $requiredSessions): array
    {
        if ($requiredSessions <= 0) {
            return [];
        }

        if ($lessons->isEmpty()) {
            return array_fill(0, $requiredSessions, collect());
        }

        $count = $lessons->count();
        $distribution = [];

        if ($requiredSessions >= $count) {
            for ($slot = 0; $slot < $requiredSessions; $slot++) {
                $lessonIndex = (int) floor(($slot * $count) / $requiredSessions);
                $distribution[$slot] = collect([$lessons->get(min($count - 1, $lessonIndex))]);
            }

            return $distribution;
        }

        for ($slot = 0; $slot < $requiredSessions; $slot++) {
            $start = (int) floor(($slot * $count) / $requiredSessions);
            $end = (int) floor((($slot + 1) * $count) / $requiredSessions) - 1;
            $end = max($start, $end);
            $distribution[$slot] = $lessons->slice($start, ($end - $start) + 1)->values();
        }

        return $distribution;
    }

    private static function lessonLabel(Collection $lessons): ?string
    {
        if ($lessons->isEmpty()) {
            return null;
        }

        if ($lessons->count() === 1) {
            return 'Clase '.$lessons->first()->class_number;
        }

        return 'Clases '.$lessons->first()->class_number.'-'.$lessons->last()->class_number;
    }

    private static function lessonUnit(Collection $lessons): ?string
    {
        if ($lessons->isEmpty()) {
            return null;
        }

        return $lessons
            ->pluck('unit')
            ->filter()
            ->unique()
            ->implode(' / ') ?: null;
    }

    private static function lessonContent(Collection $lessons): ?string
    {
        if ($lessons->isEmpty()) {
            return null;
        }

        return $lessons
            ->map(function (ProgramLevelLesson $lesson) use ($lessons) {
                if ($lessons->count() > 1) {
                    return 'Clase '.$lesson->class_number.': '.$lesson->content;
                }

                return $lesson->content;
            })
            ->implode(' | ');
    }
}
