<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Auditoría legible (#451): nombre en español de cada acción y entidad, y los cambios del payload como
 * "campo: antes → después". Lo que no esté en las listas se muestra humanizado, nunca en crudo.
 */
final class AuditLabel
{
    /** @var array<string, string> */
    public const ACTIONS = [
        'account.password.update' => 'Cambió su contraseña',
        'admin_user.create' => 'Creó un administrador',
        'admin_user.delete' => 'Eliminó un administrador',
        'admin_user.resend_credentials' => 'Reenvió credenciales de administrador',
        'admin_user.update' => 'Editó un administrador',
        'attendance.upsert' => 'Registró asistencia',
        'auth.login' => 'Inició sesión',
        'auth.logout' => 'Cerró sesión',
        'campus.program_price.delete' => 'Eliminó un precio de programa',
        'campus.program_price.save' => 'Guardó un precio de programa',
        'course.calendar.recalculate' => 'Recalculó el calendario del curso',
        'course.delete' => 'Eliminó un curso',
        'course.extracurricular_charges.generate' => 'Generó cuotas extracurriculares',
        'course.next_level_open' => 'Abrió el siguiente nivel',
        'course.session.extra' => 'Agregó una clase extra',
        'course.start_date_changed' => 'Cambió la fecha de inicio del curso',
        'course.update' => 'Editó un curso',
        'data.files_moved_private' => 'Movió archivos a almacenamiento privado',
        'data_health.reconcile_charges' => 'Concilió estados de cargos',
        'enrollment.delete' => 'Eliminó una inscripción',
        'enrollment.portal_renewal' => 'Renovó la inscripción desde el portal',
        'enrollment.transfer' => 'Cambió de curso',
        'enrollment.tuition_skipped' => 'Inscribió sin generar mensualidad',
        'enrollment.update' => 'Editó una inscripción',
        'enrollment.upsert' => 'Inscribió en un curso',
        'enrollment.withdraw' => 'Retiró del curso',
        'exchange_rates.manual' => 'Cargó una tasa de cambio a mano',
        'exchange_rates.sync_failed' => 'Falló la actualización de tasas',
        'exchange_rates.synced' => 'Actualizó las tasas de cambio',
        'finance.charge.create' => 'Creó un cargo',
        'finance.charge.void' => 'Anuló un cargo',
        'finance.charges.void_bulk' => 'Anuló cargos en lote',
        'finance.payment.create' => 'Registró un pago',
        'finance.payment.void' => 'Anuló un pago',
        'finance.payment_request.approve' => 'Aprobó un comprobante de pago',
        'finance.payment_request.reject' => 'Rechazó un comprobante de pago',
        'grades.set.create' => 'Cargó una evaluación',
        'grades.set.publish' => 'Publicó una evaluación a las familias',
        'grades.set.unpublish' => 'Devolvió una evaluación a borrador',
        'grades.set.update' => 'Editó una evaluación',
        'group.create' => 'Creó un grupo',
        'group.delete' => 'Eliminó un grupo',
        'group.update' => 'Editó un grupo',
        'holiday.create' => 'Creó un feriado',
        'holiday.delete' => 'Eliminó un feriado',
        'holiday.resync' => 'Reprogramó clases por feriado',
        'holiday.update' => 'Editó un feriado',
        'makeup.manual.create' => 'Registró una recuperativa manual',
        'makeups.session.cancel' => 'Canceló un bloque recuperativo',
        'makeups.session.update' => 'Editó un bloque recuperativo',
        'payment_method.create' => 'Creó un método de pago',
        'payment_method.delete' => 'Eliminó un método de pago',
        'payment_method.update' => 'Editó un método de pago',
        'portal.access.activate' => 'Activó su acceso al portal',
        'portal.access.deactivate' => 'Desactivó el acceso al portal',
        'portal.access.invite' => 'Invitó al portal',
        'program_lessons.file_templates_applied' => 'Aplicó las plantillas File A/B',
        'program_lessons.imported' => 'Importó el plan de clases',
        'programs.cefr_structure_applied' => 'Aplicó la estructura CEFR',
        'receipt.resent' => 'Reenvió un recibo',
        'representatives.merged' => 'Unió representantes duplicados',
        'session.update' => 'Editó una clase',
        'settings.billing.update' => 'Cambió la configuración de cobro',
        'student.attachment.create' => 'Subió un documento del alumno',
        'student.attachment.delete' => 'Eliminó un documento del alumno',
        'student.create' => 'Creó un alumno',
        'student.delete' => 'Eliminó un alumno',
        'student.teacher_observations.update' => 'Editó las observaciones del docente',
        'student.update' => 'Editó un alumno',
        'students.bulk_import' => 'Importó alumnos',
        'students.bulk_payment_reminder' => 'Envió recordatorios de pago',
        'students.historical.activate' => 'Reactivó un alumno histórico',
        'students.historical.import' => 'Importó alumnos históricos',
        'students.move_to_historical' => 'Pasó un alumno a histórico',
        'teacher.access.deactivate' => 'Desactivó el acceso del profesor',
        'teacher.access.invite' => 'Invitó al profesor al portal',
        'teacher.create' => 'Creó un profesor',
        'teacher.delete' => 'Eliminó un profesor',
        'teacher.update' => 'Editó un profesor',
        'teacher.user_email_synced' => 'Actualizó el correo de acceso del profesor',
    ];

    /** @var array<string, string> */
    public const ENTITIES = [
        'Student' => 'Alumno', 'Teacher' => 'Profesor', 'Course' => 'Curso', 'Group' => 'Grupo',
        'Enrollment' => 'Inscripción', 'Charge' => 'Cargo', 'Payment' => 'Pago', 'Receipt' => 'Recibo',
        'ChargePaymentRequest' => 'Comprobante de pago', 'MakeupRequest' => 'Recuperativa', 'MakeupSession' => 'Bloque recuperativo',
        'GradeEvaluationSet' => 'Evaluación', 'Holiday' => 'Feriado', 'ClassSession' => 'Clase', 'User' => 'Usuario',
        'Campus' => 'Sede', 'PaymentMethod' => 'Método de pago', 'Representative' => 'Representante',
        'AttendanceRecord' => 'Asistencia', 'StudentAttachment' => 'Documento', 'Program' => 'Programa',
    ];

    /** @var array<string, string> */
    private const FIELDS = [
        'first_name' => 'Nombres', 'last_name' => 'Apellidos', 'email' => 'Correo', 'phone' => 'Teléfono',
        'mobile_phone' => 'Celular', 'document_id' => 'Cédula', 'status' => 'Estado', 'campus_id' => 'Sede',
        'birth_date' => 'Nacimiento', 'concept' => 'Concepto', 'amount' => 'Monto', 'currency' => 'Moneda',
        'due_date' => 'Vence', 'reason' => 'Motivo', 'name' => 'Nombre', 'start_date' => 'Inicio', 'end_date' => 'Fin',
        'teacher_id' => 'Profesor', 'period_id' => 'Período', 'schedule_template_id' => 'Horario', 'group_id' => 'Grupo',
        'program_id' => 'Programa', 'program_level_id' => 'Nivel', 'academic_hours' => 'Horas', 'capacity' => 'Cupo',
        'evaluated_on' => 'Fecha del informe', 'enrolled_at' => 'Fecha de inscripción', 'paid_at' => 'Fecha de pago',
        'reference' => 'Referencia', 'method' => 'Método', 'notes' => 'Notas', 'title' => 'Título', 'date' => 'Fecha',
        'new_status' => 'Estado', 'previous_status' => 'Estado anterior', 'published' => 'Publicada', 'released' => 'Reservas liberadas',
        'starts_at' => 'Hora de inicio', 'ends_at' => 'Hora de fin', 'notified' => 'Avisados', 'voided' => 'Anulados',
        'skipped' => 'Omitidos', 'sent' => 'Enviados', 'student_ids' => 'Alumnos', 'course_id' => 'Curso', 'student_id' => 'Alumno',
        'enrollment_id' => 'Inscripción', 'charge_id' => 'Cargo', 'via' => 'Vía', 'bulk' => 'En lote', 'changes' => 'Cambios',
    ];

    /** Claves que nunca se muestran. */
    private const HIDDEN = ['password', 'token', 'remember_token', 'plain_password', 'profile_photo_path', 'user_agent'];

    public static function action(?string $code): string
    {
        return self::ACTIONS[$code] ?? Str::ucfirst(str_replace(['.', '_'], ' ', (string) $code));
    }

    public static function entity(?string $type, ?int $id = null): string
    {
        if (! $type) {
            return '—';
        }
        $base = class_basename($type);

        return (self::ENTITIES[$base] ?? Str::headline($base)).($id ? ' #'.$id : '');
    }

    public static function field(string $key): string
    {
        return self::FIELDS[$key] ?? Str::ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Cambios del registro en filas legibles: ['field' => ..., 'from' => ?string, 'to' => string].
     * Entiende before/after, from/to, previous_status/new_status y, si no, lista los datos guardados.
     *
     * @return list<array{field: string, from: ?string, to: string}>
     */
    public static function changes(AuditLog $log, int $limit = 8): array
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $rows = [];

        if (isset($payload['previous_status'], $payload['new_status'])) {
            $rows[] = ['field' => 'Estado', 'from' => self::value('status', $payload['previous_status']), 'to' => self::value('status', $payload['new_status'])];
            unset($payload['previous_status'], $payload['new_status']);
        }
        foreach ([['before', 'after'], ['from', 'to']] as [$old, $new]) {
            if (array_key_exists($old, $payload) && array_key_exists($new, $payload) && ! is_array($payload[$old]) && ! is_array($payload[$new])) {
                $rows[] = ['field' => 'Valor', 'from' => self::value($old, $payload[$old]), 'to' => self::value($new, $payload[$new])];
                unset($payload[$old], $payload[$new]);
            }
        }
        if (isset($payload['before']) && is_array($payload['before'])) {
            foreach ($payload['before'] as $key => $value) {
                $rows[] = ['field' => self::field((string) $key).' (antes)', 'from' => null, 'to' => self::value((string) $key, $value)];
            }
            unset($payload['before']);
        }

        foreach ($payload as $key => $value) {
            if (in_array($key, self::HIDDEN, true) || $value === null || $value === '' || $value === []) {
                continue;
            }
            $rows[] = ['field' => self::field((string) $key), 'from' => null, 'to' => self::value((string) $key, $value)];
        }

        return array_slice($rows, 0, $limit);
    }

    private static function value(string $key, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        if (is_array($value)) {
            return array_is_list($value) && count($value) <= 6 && collect($value)->every(fn ($item) => is_scalar($item))
                ? implode(', ', $value)
                : count($value).' elemento(s)';
        }
        $text = (string) $value;
        if (in_array($key, ['status', 'new_status', 'previous_status'], true)) {
            return StatusLabel::label($text);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?/', $text)) {
            try {
                $date = Carbon::parse($text);

                return strlen($text) > 10 ? $date->format('d/m/Y H:i') : $date->format('d/m/Y');
            } catch (\Throwable) {
                return $text;
            }
        }

        return Str::limit($text, 80);
    }
}
