<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menú principal por tareas. Una sola definición para el menú de escritorio y el menú móvil (☰).
 */
class Navigation
{
    /**
     * @return array<int, array{label: string, icon: string, url: ?string, active: bool, children: array<int, array{label: string, url: string, active: bool}>}>
     */
    public static function for(?User $user, ?string $currentRoute): array
    {
        if (! $user) {
            return [];
        }

        $isAdmin = $user->role === User::ROLE_ADMIN;
        $isMaster = $user->isMasterAdmin();
        $current = (string) $currentRoute;

        $items = [
            self::link('Inicio', 'home', 'dashboard', ['dashboard'], true, $current),
            self::link('Alumnos', 'students', 'students.index', ['students.', 'enrollments.'], $isAdmin, $current),
            self::link('Cursos', 'courses', 'courses.index', ['courses.', 'groups.', 'sessions.', 'program-levels.'], $isAdmin, $current),
            self::link('Asistencia', 'attendance', 'attendance.index', ['attendance.'], in_array($user->role, [User::ROLE_ADMIN, User::ROLE_TEACHER], true), $current),
            self::link('Finanzas', 'finance', 'finance.index', ['finance.index', 'finance.students.', 'finance.receipts.'], $isAdmin && $user->hasPermission('finance.manage'), $current),
            self::link('Recuperativas', 'makeups', 'makeups.index', ['makeups.'], $isAdmin, $current),
            self::group('Reportes', 'reports', $current, self::reportLinks($user)),
            self::group('Configuración', 'config', $current, self::configLinks($user, $current, withHeadings: true)),
        ];

        return array_values(array_filter($items, fn ($item) => $item !== null));
    }

    /**
     * Configuración agrupada por tema (#247). Cada grupo alimenta su barra de pestañas.
     *
     * @return array<string, array<int, array{label: string, url: string, active: bool}>>
     */
    public static function configGroups(User $user, ?string $currentRoute = null): array
    {
        $current = (string) ($currentRoute ?? request()->route()?->getName());
        $isAdmin = $user->role === User::ROLE_ADMIN;
        $isMaster = $user->isMasterAdmin();

        $groups = [
            'Personas' => [
                self::child('Profesores', 'teachers.index', ['teachers.'], $isAdmin, $current),
                self::child('Usuarios admin', 'admin-users.index', ['admin-users.'], $isMaster, $current),
            ],
            'Calendario' => [
                self::child('Feriados y días sin clase', 'holidays.index', ['holidays.'], $isAdmin, $current),
                self::child('Períodos', 'periods.index', ['periods.'], $isMaster, $current),
                self::child('Horarios', 'schedules.index', ['schedules.'], $isMaster, $current),
            ],
            'Programas' => [
                self::child('Programas y niveles', 'programs.index', ['programs.', 'program-levels.', 'program-level-lessons.'], $isMaster, $current),
                self::child('Niveles académicos', 'academic-levels.index', ['academic-levels.'], $isMaster, $current),
                self::child('Escalas de curso', 'course-levels.index', ['course-levels.'], $isMaster, $current),
            ],
            'Pagos' => [
                self::child('Métodos de pago', 'settings.payment-methods.index', ['settings.payment-methods.'], $isMaster, $current),
                self::child('Pago de recuperativas', 'settings.makeup-payment-instructions.edit', ['settings.makeup-payment-instructions.'], $isMaster, $current),
                self::child('Tarifa mensual', 'settings.billing.edit', ['settings.billing.'], $isMaster, $current),
            ],
            'Sedes' => [
                self::child('Sedes', 'campuses.index', ['campuses.'], $isMaster, $current),
            ],
        ];

        return array_filter(array_map(fn ($links) => array_values(array_filter($links)), $groups));
    }

    /** Enlaces de configuración planos; con encabezados de grupo para el menú desplegable. */
    public static function configLinks(User $user, string $current, bool $withHeadings = false): array
    {
        $links = [];
        $groups = self::configGroups($user, $current);
        foreach ($groups as $heading => $children) {
            if ($withHeadings && count($groups) > 1) {
                $links[] = ['label' => $heading, 'url' => null, 'active' => false, 'heading' => true];
            }
            array_push($links, ...$children);
        }

        return $links;
    }

    /** Grupo de configuración al que pertenece la pantalla actual (para mostrar sus pestañas). */
    public static function currentConfigGroup(User $user, ?string $currentRoute = null): ?array
    {
        foreach (self::configGroups($user, $currentRoute) as $heading => $children) {
            if (collect($children)->contains('active', true)) {
                return ['heading' => $heading, 'links' => $children];
            }
        }

        return null;
    }

    /** Reportes disponibles para el usuario; también alimenta la barra de navegación entre reportes. */
    public static function reportLinks(User $user, ?string $currentRoute = null): array
    {
        $current = (string) ($currentRoute ?? request()->route()?->getName());
        $canView = $user->role === User::ROLE_ADMIN && $user->hasPermission('reports.view');

        return array_values(array_filter([
            self::child('Asistencia', 'reports.attendance', ['reports.attendance'], $canView, $current),
            self::child('Cobranza y pagos', 'reports.payments', ['reports.payments'], $canView, $current),
            self::child('Resumen financiero', 'finance.summary', ['finance.summary'], $user->role === User::ROLE_ADMIN && $user->hasPermission('finance.manage'), $current),
            self::child('Totales por sede', 'reports.campus-totals', ['reports.campus-totals'], $canView, $current),
            self::child('Proyección por sede', 'reports.campus-projection', ['reports.campus-projection'], $canView && $user->isMasterAdmin(), $current),
            self::child('Renovación de niveles', 'reports.level-renewals', ['reports.level-renewals'], $canView, $current),
            self::child('Auditoría', 'reports.audit', ['reports.audit'], $user->role === User::ROLE_ADMIN && $user->hasPermission('audit.view'), $current),
        ]));
    }

    private static function link(string $label, string $icon, string $route, array $prefixes, bool $enabled, string $current): ?array
    {
        if (! $enabled || ! Route::has($route)) {
            return null;
        }

        return ['label' => $label, 'icon' => $icon, 'url' => route($route), 'active' => self::matches($current, $prefixes), 'children' => []];
    }

    private static function group(string $label, string $icon, string $current, array $children): ?array
    {
        $children = array_values($children);
        if ($children === []) {
            return null;
        }

        if (collect($children)->whereNull('heading')->isEmpty()) {
            return null;
        }

        return ['label' => $label, 'icon' => $icon, 'url' => null, 'active' => collect($children)->contains('active', true), 'children' => $children];
    }

    private static function child(string $label, string $route, array $prefixes, bool $enabled, string $current): ?array
    {
        if (! $enabled || ! Route::has($route)) {
            return null;
        }

        return ['label' => $label, 'url' => route($route), 'active' => self::matches($current, $prefixes)];
    }

    private static function matches(string $current, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($current === $prefix || (str_ends_with($prefix, '.') && str_starts_with($current, $prefix))) {
                return true;
            }
        }

        return false;
    }
}
