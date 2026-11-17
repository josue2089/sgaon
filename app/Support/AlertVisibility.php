<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Resumen de alertas abiertas para la campana y el inicio: total y las 8 más recientes. #454
     * Se calcula una vez por petición y se guarda 60 segundos por usuario.
     *
     * @return array{count: int, latest: Collection<int, Alert>}
     */
    public static function summaryFor(?User $user): array
    {
        if (! $user) {
            return ['count' => 0, 'latest' => collect()];
        }

        self::$memo ??= new \WeakMap;

        return self::$memo[$user] ??= Cache::remember(
            'alerts-summary:'.$user->id,
            60,
            fn () => [
                'count' => self::openFor($user)->count(),
                'latest' => self::openFor($user)->latest()->take(8)->get(),
            ],
        );
    }

    /** @var \WeakMap<User, array>|null */
    private static ?\WeakMap $memo = null;

    public static function flush(): void
    {
        self::$memo = null;
    }
}
