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

            self::syncManagedGroup($course);

            return $course->fresh(['teacher', 'period', 'scheduleTemplate', 'managedGroup']);
        }

        $schedule = $course->scheduleTemplate;
        if (! $schedule) {
            throw ValidationException::withMessages([
                'schedule_template_id' => 'El horario seleccionado no existe.',
            ]);
        }

        if (! $regenerateSessions) {
            self::syncManagedGroup($course);

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
        $group = $course->managedGroup ?: new Group();

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

    private static function syncSessions(Course $course, Group $group, ScheduleTemplate $schedule, int $requiredSessions): ?Carbon
    {
        $existingSessions = $group->sessions()
            ->withCount(['attendanceRecords', 'makeupRequests'])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get();

        $protectedSessions = $existingSessions->filter(fn (ClassSession $session) => self::sessionIsProtected($session));

        if ($protectedSessions->isNotEmpty()) {
            $firstExistingDate = $existingSessions->first()?->session_date?->toDateString();
            if ($firstExistingDate !== $course->start_date?->toDateString()) {
                throw ValidationException::withMessages([
                    'start_date' => 'El curso ya tiene sesiones con asistencia registrada. No se puede regenerar el calendario automáticamente.',
                ]);
            }
        }

        if ($existingSessions->isEmpty()) {
            return self::insertFreshSessions($course, $group, $schedule, $requiredSessions);
        }

        return self::mergeSessions($course, $group, $schedule, $requiredSessions, $existingSessions);
    }

    private static function sessionIsProtected(ClassSession $session): bool
    {
        return ($session->attendance_records_count ?? 0) > 0
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
        $holidays = Holiday::query()
            ->active()
            ->forCampus($course->campus_id)
            ->get();

        $dates = self::buildScheduleDates($course->start_date, $schedule, $requiredSessions, $holidays);
        $plannedLessons = $course->programLevel?->lessons()->orderBy('sort_order')->get() ?? collect();
        $distribution = self::distributeLessons($plannedLessons, $requiredSessions);

        $availableByDate = $existingSessions
            ->groupBy(fn (ClassSession $session) => $session->session_date?->toDateString() ?? '');

        $usedSessionIds = [];

        foreach ($dates as $index => $date) {
            $dateKey = $date->toDateString();
            $assignedLessons = $distribution[$index] ?? collect();
            $attributes = self::sessionAttributes(
                $course,
                $group,
                $schedule,
                $date,
                $index + 1,
                $assignedLessons,
                includeTimestamps: false,
            );

            $candidate = ($availableByDate->get($dateKey) ?? collect())
                ->first(fn (ClassSession $session) => ! in_array($session->id, $usedSessionIds, true));

            if ($candidate) {
                $usedSessionIds[] = $candidate->id;
                $update = $attributes;
                if (self::sessionIsProtected($candidate)) {
                    unset($update['topic'], $update['program_status'], $update['program_notes']);
                }
                $candidate->update($update);

                continue;
            }

            ClassSession::create($attributes);
        }

        foreach ($existingSessions as $session) {
            if (in_array($session->id, $usedSessionIds, true)) {
                continue;
            }

            if (self::sessionIsProtected($session)) {
                $session->update([
                    'starts_at' => $schedule->starts_at,
                    'ends_at' => $schedule->ends_at,
                ]);

                continue;
            }

            $session->delete();
        }

        $lastDate = $dates->last();

        return $lastDate instanceof Carbon ? $lastDate : null;
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

    private static function buildScheduleDates(Carbon $startDate, ScheduleTemplate $schedule, int $requiredSessions, Collection $holidays): Collection
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
