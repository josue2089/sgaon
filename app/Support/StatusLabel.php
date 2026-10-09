<?php

namespace App\Support;

use App\Models\MakeupRequest;

/**
 * Etiqueta en español y tono de badge para los estados guardados en inglés.
 * Dominio opcional para los casos en que el mismo valor se nombra distinto (p. ej. recuperativas).
 */
class StatusLabel
{
    private const LABELS = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'completed' => 'Completado',
        'graduated' => 'Graduado',
        'withdrawn' => 'Retirado',
        'cancelled' => 'Cancelado',
        'full' => 'Lleno',
        'open' => 'Abierto',
        'closed' => 'Cerrado',
        'resolved' => 'Resuelto',
        'draft' => 'Borrador',
        'planned' => 'Planificado',
        'pending' => 'Pendiente',
        'partial' => 'Abonado',
        'overdue' => 'Vencido',
        'paid' => 'Pagado',
        'voided' => 'Anulado',
        'confirmed' => 'Confirmado',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'present' => 'Presente',
        'absent' => 'Ausente',
        'late' => 'Tarde',
        'justified' => 'Justificado',
        'reserved' => 'Reservado',
        'attended' => 'Asistió',
        'missed' => 'No asistió',
        'queued' => 'En cola',
        'processing' => 'Procesando',
        'failed' => 'Falló',
        'skipped' => 'Omitido',
        'imported' => 'Importado',
        'pending_validation' => 'Por validar',
        'void' => 'Anulado',
        'running' => 'En proceso',
        'done' => 'Listo',
    ];

    private const DOMAIN_LABELS = [
        'enrollment' => ['active' => 'Activa', 'inactive' => 'Inactiva', 'completed' => 'Completada', 'withdrawn' => 'Retirada', 'cancelled' => 'Cancelada'],
        'campus' => ['active' => 'Activa', 'inactive' => 'Inactiva'],
        'attendance' => ['justified' => 'Justificada'],
        'booking' => ['reserved' => 'Reservada', 'attended' => 'Asistió', 'missed' => 'No asistió', 'cancelled' => 'Cancelada'],
    ];

    private const TONES = [
        'ok' => ['active', 'paid', 'confirmed', 'approved', 'present', 'completed', 'graduated', 'attended', 'resolved', 'booked', 'approved_for_booking', 'done'],
        'danger' => ['overdue', 'absent', 'rejected', 'voided', 'void', 'failed', 'missed', 'withdrawn'],
        'warn' => ['pending', 'partial', 'late', 'pending_payment', 'pending_validation', 'open', 'full', 'draft', 'queued', 'processing', 'running'],
    ];

    public static function label(?string $status, ?string $domain = null): string
    {
        $status = (string) $status;
        if ($status === '') {
            return '—';
        }

        if ($domain === 'makeup' && isset(MakeupRequest::STATUS_LABELS[$status])) {
            return MakeupRequest::STATUS_LABELS[$status];
        }

        return self::DOMAIN_LABELS[$domain][$status]
            ?? self::LABELS[$status]
            ?? MakeupRequest::STATUS_LABELS[$status]
            ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function tone(?string $status): string
    {
        foreach (self::TONES as $tone => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $tone;
            }
        }

        return 'info';
    }

    public static function role(?string $role): string
    {
        return [
            'admin' => 'Administrador',
            'teacher' => 'Profesor',
            'student' => 'Alumno',
            'representative' => 'Representante',
        ][(string) $role] ?? ucfirst((string) $role);
    }

    public static function category(?string $category): string
    {
        return [
            'general' => 'General',
            'payment_proof' => 'Comprobante de pago',
            'medical_support' => 'Soporte médico',
        ][(string) ($category ?: 'general')] ?? ucfirst(str_replace('_', ' ', (string) $category));
    }

    public static function chargeType(?string $type): string
    {
        return [
            'tuition' => 'Mensualidad',
            'materials' => 'Materiales',
            'registration' => 'Inscripción',
            'makeup' => 'Recuperativa',
            'other' => 'Otro',
        ][(string) $type] ?? ($type ? ucfirst(str_replace('_', ' ', $type)) : 'N/D');
    }

    /** Opciones para un <select>: valor => etiqueta. */
    public static function options(array $statuses, ?string $domain = null): array
    {
        return collect($statuses)->mapWithKeys(fn ($status) => [$status => self::label($status, $domain)])->all();
    }
}
