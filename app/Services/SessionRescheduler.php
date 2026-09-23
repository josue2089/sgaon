<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Group;
use App\Models\Holiday;
use Carbon\Carbon;
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

        $session->session_date = $newDate->toDateString();
        $this->markMoved($session, $newDate);
        $session->save();

        $this->syncEndDates($group);
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
