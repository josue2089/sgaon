<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\MakeupRequest;

/**
 * Bitácora File A/B de un alumno en un curso, con el formato de la plantilla del cliente:
 * encabezado (nivel, profesor, fechas, horario, alumno, cédula y representante), la tabla clase a clase
 * con fecha, unidad o actividad y asistencia (las firmas se dejan en blanco) y la tabla de contenido
 * adicional, que se llena con las recuperativas del curso. #470
 */
final class StudentLogbook
{
    public const EXTRA_ROWS = 8;

    /** @return array<string, mixed> */
    public static function build(Enrollment $enrollment): array
    {
        $enrollment->loadMissing([
            'student.representatives',
            'group.course.programLevel.program',
            'group.course.program',
            'group.course.teacher',
            'group.course.scheduleTemplate',
            'group.course.campus',
        ]);
        $course = $enrollment->group?->course;
        $level = $course?->programLevel;
        $student = $enrollment->student;
        $representative = $student?->representatives->first();

        $records = AttendanceRecord::query()
            ->where('enrollment_id', $enrollment->id)
            ->get()
            ->keyBy('class_session_id');

        $sessions = ClassSession::query()
            ->with('plannedLesson')
            ->where('group_id', $enrollment->group_id)
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get()
            ->values()
            ->map(function (ClassSession $session, int $index) use ($records) {
                $record = $records->get($session->id);

                return [
                    'number' => $session->sequence ?: $index + 1,
                    'date' => $session->session_date,
                    'unit' => $session->planned_unit ?: ($session->planned_class_label ?: 'Clase '.($index + 1)),
                    // Si la sesión agrupa varias clases ("Clases 3-4") la unidad ya las lista todas; la actividad solo se muestra con una.
                    'activity' => $session->plannedLesson
                        && ! str_starts_with((string) $session->planned_class_label, 'Clases ')
                        && $session->plannedLesson->content !== $session->planned_unit
                            ? $session->plannedLesson->content
                            : null,
                    'type' => $session->plannedLesson?->typeLabel(),
                    'topic' => $session->topic,
                    'attendance' => $record ? StatusLabel::label($record->status) : null,
                    'attendance_status' => $record?->status,
                ];
            });

        $makeups = MakeupRequest::query()
            ->with(['booking.makeupSession.teacher', 'missedSession'])
            ->where('enrollment_id', $enrollment->id)
            ->whereHas('booking', fn ($query) => $query->whereIn('status', ['reserved', 'attended']))
            ->get()
            ->map(fn (MakeupRequest $makeup) => [
                'content' => 'Recuperativa'.($makeup->missedSession?->session_date ? ' de la clase del '.$makeup->missedSession->session_date->format('d/m/Y') : ''),
                'date' => $makeup->booking?->makeupSession?->session_date,
                'teacher' => $makeup->booking?->makeupSession?->teacher?->full_name,
            ])
            ->sortBy(fn (array $row) => $row['date']?->timestamp ?? PHP_INT_MAX)
            ->values();

        $part = $level ? LessonTemplates::partFor($level) : null;
        $series = $level ? LessonTemplates::seriesFor($level) : null;

        return [
            'enrollment' => $enrollment,
            'student' => $student,
            'course' => $course,
            'title' => trim(($series ? LessonTemplates::seriesLabel($series) : ($level?->program?->name ?? $course?->name ?? 'Curso')).' '.($part ? 'File '.$part : '')),
            'levelName' => $level?->name ?? $course?->name,
            'teacher' => $course?->teacher?->full_name,
            'startDate' => $course?->start_date,
            'endDate' => $course?->end_date,
            'schedule' => $course?->scheduleTemplate
                ? preg_replace('/(\d{1,2}:\d{2}):\d{2}/', '$1', $course->scheduleTemplate->display_label)
                : null,
            'representative' => $representative,
            'representativePhone' => $representative?->mobile_phone ?: $representative?->phone,
            'campusName' => $course?->campus?->name,
            'sessions' => $sessions,
            'makeups' => $makeups,
            'summary' => [
                'present' => $records->whereIn('status', ['present', 'late'])->count(),
                'absent' => $records->whereIn('status', ['absent', 'justified'])->count(),
                'total' => $sessions->count(),
            ],
        ];
    }

    /** Descarga del PDF; la usan la ficha del alumno y los dos portales. */
    public static function download(Enrollment $enrollment): \Symfony\Component\HttpFoundation\Response
    {
        $logo = is_file(public_path('images/logo.png')) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo.png'))) : null;

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('students.logbook-pdf', self::build($enrollment) + ['logoDataUri' => $logo])
            ->setPaper('a4', 'landscape')
            ->download(self::filename($enrollment));
    }

    public static function filename(Enrollment $enrollment): string
    {
        $enrollment->loadMissing(['student', 'group.course.programLevel']);
        $level = $enrollment->group?->course?->programLevel?->code ?? 'curso';

        return 'bitacora-'.str($enrollment->student?->full_name ?? 'alumno')->slug().'-'.str($level)->slug().'.pdf';
    }
}
