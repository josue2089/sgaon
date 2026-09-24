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
            $this->afterMove($group);
        });
    }

    /**
     * Mueve la clase a $newDate y empuja las clases siguientes del grupo que choquen:
     * cada una pasa al próximo día válido del horario (sin feriados), con su asistencia,
     * tema y observaciones. El corrimiento se detiene cuando encuentra un hueco.
     *
     * @return int Cantidad de clases siguientes que se corrieron.
     */
    public function rescheduleWithCascade(ClassSession $session, Carbon $newDate): int
    {
        $group = $session->group()->firstOrFail();
        $this->assertDateAllowed($group, $newDate);

        $course = Course::query()->with('scheduleTemplate')->where('managed_group_id', $group->id)->first();
        if (! $course || ! $course->scheduleTemplate) {
            throw ValidationException::withMessages([
                'session_date' => 'Este grupo no tiene un horario asignado, así que no se pueden correr las clases siguientes. Mueve solo esta clase.',
            ]);
        }

        $holidays = CoursePlanner::holidaysFor($course);
        $groupSessions = ClassSession::query()->where('group_id', $group->id)->get();
        $excludedDates = CoursePlanner::excludedDates($groupSessions);
        $oldDate = $session->session_date?->toDateString();
        if ($oldDate && $oldDate !== $newDate->toDateString()) {
            $excludedDates[] = $oldDate;
        }

        $chain = ClassSession::query()
            ->where('group_id', $group->id)
            ->where('id', '!=', $session->id)
            ->whereDate('session_date', '>=', $newDate->toDateString())
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $moves = [];
        $previous = $newDate->copy()->startOfDay();
        foreach ($chain as $other) {
            $current = $other->session_date->copy()->startOfDay();
            if ($current->gt($previous)) {
                $previous = $current;

                continue;
            }

            $next = CoursePlanner::scheduleDateAfter($course, $previous, $holidays, $excludedDates);
            if (! $next) {
                throw ValidationException::withMessages([
                    'session_date' => 'No se encontró una fecha disponible en el horario para correr las clases siguientes.',
                ]);
            }
            $moves[$other->id] = $next;
            $previous = $next;
        }

        DB::transaction(function () use ($session, $group, $newDate, $moves): void {
            // Fechas temporales únicas para no chocar con el índice (grupo, fecha, hora) mientras se reordena.
            // La clase que se mueve no lo necesita: su fecha original nunca es destino de otra.
            foreach (array_keys($moves) as $id) {
                ClassSession::query()->whereKey($id)->update([
                    'session_date' => Carbon::create(1900, 1, 1)->addDays($id)->toDateString(),
                ]);
            }

            foreach ($moves as $id => $date) {
                ClassSession::query()->whereKey($id)->update(['session_date' => $date->toDateString()]);
            }

            $session->session_date = $newDate->toDateString();
            $this->markMoved($session, $newDate);
            $session->save();
            $this->afterMove($group);
        });

        return count($moves);
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
                        'Ya hay otra clase de este grupo el %s a las %s con asistencia registrada. Elige otra fecha u hora, o marca "Correr también las clases siguientes" en Asistencia.',
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
     * Debe llamarse dentro de la misma transacción que el movimiento: si el recálculo falla,
     * se lanza el error y se revierte todo (así nunca queda una clase planificada borrada sin reponer).
     */
    public function afterMove(Group $group): void
    {
        $course = Course::query()->where('managed_group_id', $group->id)->first();

        if ($course && $course->status === 'active' && $course->schedule_template_id && $course->start_date && $course->academic_hours) {
            try {
                CoursePlanner::sync($course, true);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'session_date' => 'No se pudo mover la clase porque el calendario del curso no se puede recalcular: '
                        .(collect($exception->errors())->flatten()->first() ?? 'datos inconsistentes.'),
                ]);
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
