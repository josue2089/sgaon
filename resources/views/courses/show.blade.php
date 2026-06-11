@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">{{ $course->name }}</h1>
        <p class="page-subtitle">Detalle operativo del curso, sesiones y avance del programa</p>
    </div>
    <div class="form-actions">
        @if(auth()->user()?->isMasterAdmin())
            <a class="btn secondary" href="{{ route('courses.report.pdf', $course) }}">Descargar PDF</a>
        @endif
        <a class="btn" href="{{ route('courses.grades.index', $course) }}">Evaluaciones / Notas</a>
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
            <div><strong>Etapa:</strong> {{ $course->level?->name ?? 'N/D' }}</div>
            <div><strong>Programa:</strong> {{ $course->program?->name ?? 'N/D' }}</div>
            <div><strong>Nivel real:</strong> {{ $course->programLevel ? $course->programLevel->sort_order.'/'.$course->programLevel->program_total.' · '.$course->programLevel->name : ($course->courseLevel?->name ?? 'N/D') }}</div>
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
            <form method="POST" action="{{ route('courses.students.sync', $course) }}" data-student-picker>
                @csrf
                <div class="stack-sm student-picker">
                    <div class="student-picker-summary">
                        <div>
                            <div class="table-title">Selección de alumnos</div>
                            <div class="table-sub">
                                {{ $availableStudents->count() }} disponibles para este curso
                            </div>
                        </div>
                        <div class="student-picker-summary-meta" data-picker-count>0 seleccionados</div>
                    </div>
                    <div class="form-actions">
                        <button class="btn secondary" type="button" data-picker-open>Seleccionar estudiantes</button>
                        <button class="btn" type="submit">Agregar estudiantes</button>
                    </div>
                    <div class="student-picker-selection" data-picker-selection>Sin alumnos seleccionados.</div>
                    @error('student_ids')
                        <div class="flash err">{{ $message }}</div>
                    @enderror
                </div>

                <div class="student-picker-modal" data-picker-modal hidden>
                    <div class="student-picker-backdrop" data-picker-close></div>
                    <div class="student-picker-dialog card">
                        <div class="student-picker-head">
                            <div>
                                <h3 class="section-title">Seleccionar estudiantes</h3>
                                <p class="page-subtitle">Solo se muestran alumnos activos del campus del curso que aún no están inscritos.</p>
                            </div>
                            <button class="btn secondary" type="button" data-picker-close>Cerrar</button>
                        </div>

                        @if($availableStudents->count() > 0)
                            <div class="student-picker-toolbar">
                                <input type="text" placeholder="Buscar por nombre o email" data-picker-search>
                                <label class="student-picker-toggle">
                                    <input type="checkbox" data-picker-toggle-all>
                                    <span>Seleccionar visibles</span>
                                </label>
                            </div>

                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                    <tr>
                                        <th></th>
                                        <th>Alumno</th>
                                        <th>Email</th>
                                        <th>Nivel actual</th>
                                        <th>Estatus</th>
                                    </tr>
                                    </thead>
                                    <tbody data-picker-rows>
                                    @foreach($availableStudents as $student)
                                        @php
                                            $currentEnrollment = $student->enrollments->firstWhere('status', 'active') ?: $student->enrollments->first();
                                            $currentLevel = $currentEnrollment?->group?->course?->programLevel ?: $currentEnrollment?->group?->course?->courseLevel;
                                        @endphp
                                        <tr data-picker-row data-search="{{ strtolower($student->full_name.' '.$student->email) }}">
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    name="student_ids[]"
                                                    value="{{ $student->id }}"
                                                    data-picker-checkbox
                                                    @checked(in_array($student->id, old('student_ids', [])))
                                                >
                                            </td>
                                            <td>
                                                <div class="table-user">
                                                    <span class="table-avatar">
                                                        @if($student->profile_photo_path)
                                                            <img src="{{ asset('storage/'.$student->profile_photo_path) }}" alt="{{ $student->full_name }}">
                                                        @else
                                                            {{ strtoupper(substr($student->first_name, 0, 1)) }}
                                                        @endif
                                                    </span>
                                                    <div>
                                                        <div class="table-title">{{ $student->full_name }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $student->email ?: 'Sin email' }}</td>
                                            <td>{{ $currentLevel ? (($currentLevel->sort_order ?? $currentLevel->scale_position).'/'.($currentLevel->program_total ?? $currentLevel->scale_total).' · '.$currentLevel->name) : 'N/D' }}</td>
                                            <td>@include('partials.ui.status-badge', ['tone' => $student->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($student->status)])</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="form-actions">
                                <button class="btn secondary" type="button" data-picker-close>Listo</button>
                                <button class="btn" type="submit">Agregar estudiantes</button>
                            </div>
                        @else
                            <div class="empty-state">No hay alumnos activos disponibles para agregar a este curso.</div>
                        @endif
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
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($group->enrollments as $enrollment)
                    <tr>
                        <td>{{ $enrollment->student?->full_name ?? 'N/D' }}</td>
                        <td>{{ $enrollment->student?->email ?: 'Sin email' }}</td>
                        <td>{{ ucfirst($enrollment->status) }}</td>
                        <td>{{ (int) $enrollment->progress }}%</td>
                        <td class="table-actions">
                            <form method="POST" action="{{ route('courses.students.remove', [$course, $enrollment]) }}" onsubmit="return confirm('¿Quitar este alumno del curso?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn-link-danger" type="submit">Quitar</button>
                            </form>
                        </td>
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
                    <th>Programa planificado</th>
                    <th>Programa ejecutado</th>
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
                        <td>
                            <div class="table-title">{{ $session->planned_class_label ?: 'Clase pendiente' }}</div>
                            <div class="table-sub">{{ $session->planned_unit ?: 'Sin unidad' }}</div>
                            <div class="table-sub">{{ $session->planned_content ?: 'Sin contenido planificado' }}</div>
                        </td>
                        <td>{{ $session->topic ?: 'Sin ejecución cargada' }}</td>
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
                            @if($session->canRecordAttendance())
                                <a href="{{ route('attendance.index', ['class_session_id' => $session->id]) }}">Asistencia</a>
                            @else
                                <a href="{{ route('attendance.index', ['class_session_id' => $session->id]) }}">Ver sesión</a>
                            @endif
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
