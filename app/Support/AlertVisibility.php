<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qué alertas abiertas ve cada rol en la campana y en el inicio. #439
 * - Admin: las de sus sedes.
 * - Profesor: solo las de asistencia de alumnos activos en sus grupos (nada de finanzas).
 * - Alumno y representante: ninguna; lo suyo lo ven en el portal.
 */
final class AlertVisibility
{
    public static function openFor(?User $user): Builder
    {
        $query = Alert::query()->where('status', 'open');

        if ($user?->role === User::ROLE_ADMIN) {
            return CampusScope::apply($query, $user);
        }

        if ($user?->role === User::ROLE_TEACHER && ($teacher = Teacher::forUser($user))) {
            return CampusScope::apply($query, $user)
                ->where('type', 'attendance')
                ->whereIn('student_id', Enrollment::query()
                    ->select('student_id')
                    ->where('status', 'active')
                    ->whereHas('group', fn ($group) => $group->where('teacher_id', $teacher->id)));
        }

        return $query->whereRaw('1 = 0');
    }
}
