<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de curso {{ $course->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #172554; margin: 26px; }
        .header { border-bottom: 2px solid #d8e1f0; padding-bottom: 12px; margin-bottom: 18px; }
        .brand { width: 100%; }
        .brand td { vertical-align: middle; }
        .brand-logo img { width: 72px; height: auto; }
        .brand-meta { text-align: right; }
        h1 { margin: 0 0 4px; font-size: 21px; }
        .subtitle { margin: 0; font-size: 11px; color: #64748b; }
        h2 { margin: 18px 0 8px; font-size: 14px; }
        .summary-grid { width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 12px; }
        .summary-grid td { width: 25%; vertical-align: top; border: 1px solid #d8e1f0; border-radius: 8px; padding: 10px; }
        .summary-label { font-size: 9px; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
        .summary-value { font-size: 14px; font-weight: 700; }
        .detail-list { margin-bottom: 8px; }
        .detail-list div { margin-bottom: 5px; }
        .label { font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #d8e1f0; padding: 7px 6px; text-align: left; vertical-align: top; }
        table.data th { background: #eef4ff; font-size: 9px; text-transform: uppercase; }
        .muted { color: #64748b; font-size: 10px; }
        .empty { color: #64748b; font-style: italic; padding: 8px 0; }
    </style>
</head>
<body>
    <div class="header">
        <table class="brand">
            <tr>
                <td class="brand-logo">
                    @if(!empty($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="ON English">
                    @endif
                </td>
                <td class="brand-meta">
                    <div style="font-size: 16px; font-weight: 700;">ON English</div>
                    <div class="subtitle">Academy Portal · Reporte de curso</div>
                    <div class="subtitle">Generado: {{ $generatedAt->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
        <h1>{{ $course->name }}</h1>
        <p class="subtitle">{{ $course->campus?->name ?? 'Sin campus' }}</p>
    </div>

    <table class="summary-grid">
        <tr>
            <td>
                <div class="summary-label">Profesor asignado</div>
                <div class="summary-value">{{ $course->teacher?->full_name ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="summary-label">Total estudiantes</div>
                <div class="summary-value">{{ $group?->enrollments?->count() ?? 0 }}</div>
            </td>
            <td>
                <div class="summary-label">Sesiones completadas</div>
                <div class="summary-value">{{ $completedSessions }}</div>
            </td>
            <td>
                <div class="summary-label">Sesiones pendientes</div>
                <div class="summary-value">{{ $pendingSessions }}</div>
            </td>
        </tr>
    </table>

    <h2>Ficha del curso</h2>
    <div class="detail-list">
        <div><span class="label">Código:</span> {{ $course->code ?: 'Sin código' }}</div>
        <div><span class="label">Etapa:</span> {{ $course->level?->name ?? 'N/D' }}</div>
        <div><span class="label">Programa:</span> {{ $course->program?->name ?? 'N/D' }}</div>
        <div>
            <span class="label">Nivel real:</span>
            @if($course->programLevel)
                {{ $course->programLevel->sort_order }}/{{ $course->programLevel->program_total }} · {{ $course->programLevel->name }}
            @else
                {{ $course->courseLevel?->name ?? 'N/D' }}
            @endif
        </div>
        <div><span class="label">Período:</span> {{ $course->period?->code ?? 'N/D' }}</div>
        <div><span class="label">Horario:</span> {{ $course->scheduleTemplate?->display_label ?? 'N/D' }}</div>
        <div><span class="label">Fecha inicio:</span> {{ $course->start_date?->format('d/m/Y') ?? 'N/D' }}</div>
        <div><span class="label">Fecha fin:</span> {{ $course->end_date?->format('d/m/Y') ?? 'N/D' }}</div>
        <div><span class="label">Duración:</span> {{ $course->academic_hours ? $course->academic_hours.' horas académicas' : 'N/D' }}</div>
        <div><span class="label">Grupo operativo:</span> {{ $group?->name ?? 'No generado' }}</div>
    </div>

    <h2>Estudiantes del curso</h2>
    @if($group && $group->enrollments->count() > 0)
        <table class="data">
            <thead>
            <tr>
                <th>Alumno</th>
                <th>Email</th>
                <th>Estatus</th>
                <th>Progreso</th>
            </tr>
            </thead>
            <tbody>
            @foreach($group->enrollments as $enrollment)
                <tr>
                    <td>{{ $enrollment->student?->full_name ?? 'N/D' }}</td>
                    <td>{{ $enrollment->student?->email ?: 'Sin email' }}</td>
                    <td>{{ ucfirst($enrollment->status) }}</td>
                    <td>{{ (int) $enrollment->progress }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">Todavía no hay estudiantes inscritos en este curso.</div>
    @endif

    <h2>Sesiones y programa</h2>
    @if($sessions->count() > 0)
        <table class="data">
            <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Programa planificado</th>
                <th>Programa ejecutado</th>
                <th>Estado</th>
                <th>Observación</th>
                <th>Asistencia</th>
            </tr>
            </thead>
            <tbody>
            @foreach($sessions as $session)
                <tr>
                    <td>{{ $session->sequence ?: $loop->iteration }}</td>
                    <td>{{ $session->session_date?->format('d/m/Y') ?? 'N/D' }}</td>
                    <td>{{ ($session->starts_at && $session->ends_at) ? substr($session->starts_at, 0, 5).' - '.substr($session->ends_at, 0, 5) : 'N/D' }}</td>
                    <td>
                        <div>{{ $session->planned_class_label ?: 'Clase pendiente' }}</div>
                        <div class="muted">{{ $session->planned_unit ?: 'Sin unidad' }}</div>
                        <div class="muted">{{ $session->planned_content ?: 'Sin contenido planificado' }}</div>
                    </td>
                    <td>{{ $session->topic ?: 'Sin ejecución cargada' }}</td>
                    <td>
                        @if($session->program_status === 'on_track')
                            Al día
                        @elseif($session->program_status === 'delayed')
                            Con retraso
                        @else
                            Sin indicar
                        @endif
                    </td>
                    <td>{{ $session->program_notes ?: 'Sin observación' }}</td>
                    <td>{{ ($session->attendance_records_count ?? 0) > 0 ? 'Registrada' : 'Pendiente' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No hay sesiones generadas para este curso.</div>
    @endif
</body>
</html>
