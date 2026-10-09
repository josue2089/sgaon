<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\ClassSession;
use App\Models\MakeupRequest;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Bandeja "Hoy" del dashboard: pendientes con su cantidad y un enlace directo para resolverlos.
 * Todos los conteos respetan la sede del usuario; el profesor solo ve sus clases.
 */
class TodayInbox
{
    /**
     * @return array<int, array{key: string, label: string, hint: string, count: int, url: string, action: string, tone: string}>
     */
    public static function for(User $user, ?Teacher $teacher = null): array
    {
        $today = now()->toDateString();

        $sessionsWithoutAttendance = CampusScope::apply(
            ClassSession::query()
                ->whereDate('session_date', $today)
                ->whereDoesntHave('attendanceRecords')
                ->whereHas('group.enrollments', fn ($query) => $query->where('status', 'active'))
                ->when($teacher, fn ($query) => $query->whereHas('group', fn ($group) => $group->where('teacher_id', $teacher->id))),
            $user
        )->count();

        $items = [
            self::item('attendance', 'Clases de hoy sin asistencia', 'Grupos con alumnos que todavía no tienen la asistencia tomada.', $sessionsWithoutAttendance, 'attendance.index', [], 'Tomar asistencia', 'warn'),
        ];

        if ($user->role !== User::ROLE_ADMIN) {
            return array_values(array_filter($items));
        }

        $canFinance = $user->hasPermission('finance.manage');

        if ($canFinance) {
            $proofs = CampusScope::apply(
                ChargePaymentRequest::query()->where('status', ChargePaymentRequest::STATUS_PENDING_VALIDATION),
                $user
            )->count();
            $items[] = self::item('proofs', 'Comprobantes del portal por validar', 'Pagos que enviaron alumnos o representantes desde el portal.', $proofs, 'finance.index', [], 'Validar', 'warn', '#comprobantes');
        }

        $makeupsToValidate = CampusScope::apply(MakeupRequest::query()->where('status', MakeupRequest::STATUS_PENDING_VALIDATION), $user)->count();
        $makeupsToBook = CampusScope::apply(MakeupRequest::query()->where('status', MakeupRequest::STATUS_APPROVED_FOR_BOOKING), $user)->count();
        $items[] = self::item('makeups_validate', 'Recuperativas por validar', 'Solicitudes con soporte o pago por revisar.', $makeupsToValidate, 'makeups.index', ['status' => MakeupRequest::STATUS_PENDING_VALIDATION], 'Revisar', 'warn');
        $items[] = self::item('makeups_book', 'Recuperativas por reservar', 'Aprobadas y pagadas, sin clase reservada todavía.', $makeupsToBook, 'makeups.index', ['status' => MakeupRequest::STATUS_APPROVED_FOR_BOOKING], 'Reservar', 'info');

        if ($canFinance) {
            $overdue = CampusScope::apply(
                Charge::query()
                    ->whereNull('voided_at')
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->whereDate('due_date', '<', now()->subDays(30)->toDateString()),
                $user
            );
            $items[] = self::item('overdue_30', 'Cargos vencidos hace más de 30 días', (clone $overdue)->distinct('student_id')->count('student_id').' alumnos con mora crítica.', (clone $overdue)->count(), 'reports.payments', ['status' => 'overdue'], 'Ver mora', 'danger');

            $extracurricular = CampusScope::apply(
                Charge::query()
                    ->whereNull('voided_at')
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->whereHas('course.program', fn ($query) => $query->where('is_extracurricular', true)),
                $user
            )->count();
            $items[] = self::item('extracurricular', 'Mensualidades extracurriculares del mes', 'Cuotas de este mes que siguen sin pagarse.', $extracurricular, 'finance.index', [], 'Ver cobros', 'info');
        }

        $withoutEnrollment = CampusScope::apply(
            Student::query()
                ->where('status', 'active')
                ->whereDoesntHave('enrollments', fn ($query) => $query->where('status', 'active')),
            $user
        )->count();
        $items[] = self::item('no_enrollment', 'Alumnos activos sin inscripción', 'Están activos pero no tienen curso: inscríbelos o pásalos a histórico.', $withoutEnrollment, 'students.index', ['enrollment' => 'none'], 'Ver alumnos', 'info');

        return array_values(array_filter($items));
    }

    private static function item(string $key, string $label, string $hint, int $count, string $route, array $params, string $action, string $tone, string $anchor = ''): ?array
    {
        if (! Route::has($route)) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'hint' => $hint,
            'count' => $count,
            'url' => route($route, $params).$anchor,
            'action' => $action,
            'tone' => $count > 0 ? $tone : 'ok',
        ];
    }
}
