<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Group;
use App\Models\Holiday;
use App\Support\CoursePlanner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionRescheduler
{
    public function assertDateAllowed(Group $group, Carbon $date): void
    {
        if ($group->start_date && $date->lt($group->start_date->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'session_date' => 'La sesión no puede ser antes de la fecha de inicio del grupo ('.$group->start_date->format('d/m/Y').').',
            ]);
        }

        $holiday = Holiday::query()
            ->active()
            ->forCampus((int) $group->campus_id)
            ->get()
            ->first(fn (Holiday $holiday) => $holiday->occursOn($date));

        if ($holiday) {
            throw ValidationException::withMessages([
                'session_date' => "Esa fecha es feriado ({$holiday->name}). Elige otra.",
            ]);
        }
    }

    /**
     * Marca la clase como movida a mano para que el recálculo automático la respete.
     * Guarda la fecha original solo la primera vez que se mueve.
     */
    public function markMoved(ClassSession $session, Carbon $newDate): void
    {
        $current = $session->getOriginal('session_date');
        $current = $current ? Carbon::parse($current)->toDateString() : null;

        if ($current === $newDate->toDateString()) {
            return;
        }

        $session->date_locked = true;
        if (! $session->rescheduled_from && $current) {
            $session->rescheduled_from = $current;
        }
    }

    public function reschedule(ClassSession $session, Carbon $newDate): void
    {
        $group = $session->group()->firstOrFail();
        $this->assertDateAllowed($group, $newDate);

        DB::transaction(function () use ($session, $group, $newDate): void {
            $this->freeSlot($session, (int) $group->id, $newDate, $session->starts_at);
            $session->session_date = $newDate->toDateString();
            $this->markMoved($session, $newDate);
            $session->save();
        });

        $this->afterMove($group);
    }

    /**
     * Un grupo no puede tener dos clases el mismo día a la misma hora (índice único).
     * Si la que ocupa ese lugar no tiene asistencia, es la clase planificada que la movida
     * viene a reemplazar: se elimina. Si tiene asistencia o recuperativas, se rechaza el cambio.
     */
    public function freeSlot(ClassSession $session, int $groupId, Carbon $date, ?string $startsAt): void
    {
        if (! $startsAt) {
            return;
        }

        $time = CoursePlanner::normalizeTime($startsAt);
        $occupants = ClassSession::query()
            ->where('group_id', $groupId)
            ->whereDate('session_date', $date->toDateString())
            ->when($session->exists, fn ($q) => $q->where('id', '!=', $session->id))
            ->withCount(['attendanceRecords', 'makeupRequests'])
            ->get()
            ->filter(fn (ClassSession $other) => CoursePlanner::normalizeTime($other->starts_at) === $time);

        foreach ($occupants as $other) {
            if ($other->date_locked || $other->attendance_records_count > 0 || $other->makeup_requests_count > 0) {
                throw ValidationException::withMessages([
                    'session_date' => sprintf(
                        'Ya hay otra clase de este grupo el %s a las %s con asistencia registrada. Elige otra fecha u hora.',
                        $date->format('d/m/Y'),
                        substr($time, 0, 5),
                    ),
                ]);
            }
        }

        $occupants->each->delete();
    }

    /**
     * Tras mover una clase, recalcula el curso (mantiene el total de clases y la fecha de fin).
     */
    public function afterMove(Group $group): void
    {
        $course = Course::query()->where('managed_group_id', $group->id)->first();

        if ($course && $course->status === 'active' && $course->schedule_template_id && $course->start_date && $course->academic_hours) {
            try {
                CoursePlanner::sync($course, true);
            } catch (ValidationException) {
                // Curso con datos inconsistentes (se reporta en "Recalcular calendario"); el cambio manual se conserva.
            }
        }

        $this->syncEndDates($group->fresh());
    }

    public function syncEndDates(Group $group): void
    {
        $lastDate = ClassSession::query()->where('group_id', $group->id)->max('session_date');
        $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : null;

        if ($group->end_date?->toDateString() !== $lastDate) {
            $group->forceFill(['end_date' => $lastDate])->saveQuietly();
        }

        Course::query()
            ->where('managed_group_id', $group->id)
            ->get()
            ->each(function (Course $course) use ($lastDate): void {
                if ($course->end_date?->toDateString() !== $lastDate) {
                    $course->forceFill(['end_date' => $lastDate])->saveQuietly();
                }
            });
    }
}
