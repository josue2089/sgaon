<?php

namespace App\Support;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Student;

/** Dónde está el alumno en la ruta de su programa, para mostrarlo en el portal. #473 */
final class LearningPath
{
    /** Edad a la que un alumno de High School pasa al programa de adultos (dato del cliente). */
    public const ADULT_TRANSITION_AGE = 14;

    /**
     * @return array{program: Program, level: ProgramLevel, next: ?ProgramLevel, levels: list<array{level: ProgramLevel, state: string}>, sessions_done: int, sessions_total: int, adult_transition: ?ProgramLevel}|null
     */
    public static function for(Student $student): ?array
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('group.course.programLevel.program', fn ($query) => $query->where('uses_cefr', true))
            ->with('group.course.programLevel.program.levels')
            ->latest('enrolled_at')
            ->first();
        $course = $enrollment?->group?->course;
        $level = $course?->programLevel;
        if (! $level) {
            return null;
        }

        $program = $level->program;
        $sessions = ClassSession::query()->where('group_id', $enrollment->group_id);
        $next = $level->nextLevel();

        return [
            'program' => $program,
            'level' => $level,
            'next' => $next,
            'levels' => $program->levels
                ->where('status', 'active')
                ->values()
                ->map(fn (ProgramLevel $item) => [
                    'level' => $item,
                    'state' => match (true) {
                        $item->is($level) => 'current',
                        $item->sort_order < $level->sort_order => 'done',
                        default => 'upcoming',
                    },
                ])
                ->all(),
            'sessions_done' => (clone $sessions)->whereDate('session_date', '<=', today())->count(),
            'sessions_total' => (clone $sessions)->count(),
            'adult_transition' => self::adultTransition($student, $program, $next),
        ];
    }

    /** A los 14 años un alumno de High School sigue en el nivel de adultos equivalente al que le toca. */
    private static function adultTransition(Student $student, Program $program, ?ProgramLevel $next): ?ProgramLevel
    {
        if ($program->audience !== 'teens' || ! $next || ! $student->birth_date || $student->birth_date->age < self::ADULT_TRANSITION_AGE) {
            return null;
        }

        $adults = Program::query()->where('code', Program::ADULTS_CODE)->where('status', 'active')->first();

        return $adults ? $next->equivalentIn($adults) : null;
    }
}
