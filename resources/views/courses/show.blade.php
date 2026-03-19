@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">{{ $course->name }}</h1>
        <p class="page-subtitle">Detalle operativo del curso, sesiones y avance del programa</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('courses.edit', $course) }}">Editar curso</a>
        <a class="btn secondary" href="{{ route('courses.index') }}">Volver</a>
    </div>
</div>

<div class="metric-grid metric-grid-4">
    @include('partials.ui.metric-card', ['tone' => 'metric-blue', 'iconName' => 'teacher', 'label' => 'Profesor Asignado', 'value' => $course->teacher?->full_name ?? 'N/D'])
    @include('partials.ui.metric-card', ['tone' => 'metric-green', 'iconName' => 'users', 'label' => 'Total Estudiantes', 'value' => $group?->enrollments?->count() ?? 0])
    @include('partials.ui.metric-card', ['tone' => 'metric-purple', 'iconName' => 'calendar', 'label' => 'Sesiones Completadas', 'value' => $completedSessions])
    @include('partials.ui.metric-card', ['tone' => 'metric-orange', 'iconName' => 'trend', 'label' => 'Sesiones Pendientes', 'value' => $pendingSessions])
</div>

<div class="detail-grid">
    <div class="card">
        <h2 class="section-title">Ficha del curso</h2>
        <div class="detail-list">
            <div><strong>Código:</strong> {{ $course->code ?: 'Sin código' }}</div>
            <div><strong>Nivel:</strong> {{ $course->level?->name ?? 'N/D' }}</div>
            <div><strong>Escala:</strong> {{ $course->courseLevel ? $course->courseLevel->scale_position.'/'.$course->courseLevel->scale_total.' · '.$course->courseLevel->name : 'N/D' }}</div>
            <div><strong>Período:</strong> {{ $course->period?->code ?? 'N/D' }}</div>
            <div><strong>Horario:</strong> {{ $course->scheduleTemplate?->display_label ?? 'N/D' }}</div>
            <div><strong>Fecha inicio:</strong> {{ $course->start_date?->format('d/m/Y') ?? 'N/D' }}</div>
            <div><strong>Fecha fin:</strong> {{ $course->end_date?->format('d/m/Y') ?? 'N/D' }}</div>
            <div><strong>Duración:</strong> {{ $course->academic_hours ? $course->academic_hours.' horas académicas' : 'N/D' }}</div>
            <div><strong>Grupo operativo:</strong> {{ $group?->name ?? 'No generado' }}</div>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Agregar estudiantes</h2>
        @if($group)
            <form method="POST" action="{{ route('courses.students.sync', $course) }}">
                @csrf
                <div class="stack-sm">
                    <select name="student_ids[]" multiple size="10" required>
                        @foreach($availableStudents as $student)
                            <option value="{{ $student->id }}">{{ $student->full_name }}{{ $student->email ? ' · '.$student->email : '' }}</option>
                        @endforeach
                    </select>
                    <div class="form-actions">
                        <button class="btn" type="submit">Agregar estudiantes</button>
                    </div>
                </div>
            </form>
        @else
            <div class="empty-state">Completa la configuración operativa del curso para generar el grupo y habilitar inscripciones.</div>
        @endif
    </div>
</div>

<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title">Estudiantes del curso</h2>
        <div class="entity-sub">{{ $group?->enrollments?->count() ?? 0 }} inscritos</div>
    </div>
    @if($group && $group->enrollments->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
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
        </div>
    @else
        <div class="empty-state">Todavía no hay estudiantes inscritos en este curso.</div>
    @endif
</div>

<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title">Sesiones y programa</h2>
        <div class="entity-sub">Cada fila permite ir rápido a asistencia.</div>
    </div>
    @if($sessions->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Horario</th>
                    <th>Programa</th>
                    <th>Estado programa</th>
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
                        <td>{{ $session->topic ?: 'Programa pendiente' }}</td>
                        <td>
                            @if($session->program_status === 'on_track')
                                @include('partials.ui.status-badge', ['tone' => 'ok', 'text' => 'Al día'])
                            @elseif($session->program_status === 'delayed')
                                @include('partials.ui.status-badge', ['tone' => 'warn', 'text' => 'Con retraso'])
                            @else
                                <span class="entity-sub">Sin indicar</span>
                            @endif
                        </td>
                        <td>{{ $session->program_notes ?: 'Sin observación' }}</td>
                        <td class="table-actions">
                            <a href="{{ route('attendance.index', ['class_session_id' => $session->id]) }}">Asistencia</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">No hay sesiones generadas para este curso.</div>
    @endif
</div>
@endsection
