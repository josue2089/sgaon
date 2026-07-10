@extends('layouts.app')
@section('content')
<div class="detail-hero">
    <div class="detail-hero-main">
        <div class="detail-hero-avatar">
            @if($teacher->profile_photo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($teacher->profile_photo_path) }}" alt="{{ $teacher->full_name }}">
            @else
                {{ strtoupper(substr($teacher->first_name, 0, 1)) }}
            @endif
        </div>
        <div class="detail-hero-copy">
            <div class="detail-hero-kicker">Ficha docente</div>
            <h1 class="page-title">Prof. {{ $teacher->full_name }}</h1>
            <p class="page-subtitle">Detalle operativo del profesor, sus cursos asignados y progreso académico.</p>
            <div class="detail-hero-meta">
                @include('partials.ui.status-badge', ['tone' => $teacher->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($teacher->status)])
                <span>{{ $teacher->campus?->name ?? 'Sin campus' }}</span>
                <span>{{ $teacher->email ?: 'Sin email' }}</span>
                <span>{{ $teacher->phone ?: 'Sin teléfono' }}</span>
            </div>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('teachers.edit', $teacher) }}">Editar profesor</a>
        <a class="btn secondary" href="{{ route('teachers.index') }}">Volver</a>
    </div>
</div>

<div class="metric-grid metric-grid-4">
    @include('partials.ui.metric-card', ['tone' => 'metric-purple', 'iconName' => 'book', 'label' => 'Cursos asignados', 'value' => $summary['courses_total']])
    @include('partials.ui.metric-card', ['tone' => 'metric-green', 'iconName' => 'users', 'label' => 'Estudiantes asignados', 'value' => $summary['students_total']])
    @include('partials.ui.metric-card', ['tone' => 'metric-blue', 'iconName' => 'calendar', 'label' => 'Sesiones completadas', 'value' => $summary['completed_sessions'].' / '.$summary['planned_sessions']])
    @include('partials.ui.metric-card', ['tone' => 'metric-orange', 'iconName' => 'trend', 'label' => 'Asistencia promedio', 'value' => is_null($summary['attendance_rate']) ? 'N/D' : $summary['attendance_rate'].'%'])
</div>

<div class="card">
    <div class="teacher-section-nav">
        <a href="#calendario">Calendario</a>
        <a href="#agenda">Agenda</a>
        <a href="#cursos">Cursos</a>
        <a href="#estudiantes">Estudiantes</a>
        <a href="#auditoria">Auditoría</a>
    </div>
    <form method="GET" action="{{ route('teachers.show', $teacher) }}" class="teacher-detail-filters">
        <div class="teacher-filter-chip">
            <span class="table-sub">Campus</span>
            <strong>{{ $teacher->campus?->name ?? 'N/D' }}</strong>
        </div>
        <select name="course_status">
            <option value="">Todos los cursos</option>
            <option value="active" @selected($filters['course_status'] === 'active')>Cursos activos</option>
            <option value="inactive" @selected($filters['course_status'] === 'inactive')>Cursos inactivos</option>
        </select>
        <select name="student_status">
            <option value="">Todos los alumnos</option>
            <option value="active" @selected($filters['student_status'] === 'active')>Inscripciones activas</option>
            <option value="inactive" @selected($filters['student_status'] === 'inactive')>Inscripciones inactivas</option>
            <option value="withdrawn" @selected($filters['student_status'] === 'withdrawn')>Retirados</option>
            <option value="completed" @selected($filters['student_status'] === 'completed')>Completados</option>
        </select>
        <select name="audit_action">
            <option value="">Toda la auditoría</option>
            <option value="create" @selected($filters['audit_action'] === 'create')>Altas</option>
            <option value="update" @selected($filters['audit_action'] === 'update')>Actualizaciones</option>
            <option value="delete" @selected($filters['audit_action'] === 'delete')>Eliminaciones</option>
        </select>
        <div class="form-actions">
            <button class="btn secondary" type="submit">Aplicar</button>
            <a class="btn secondary" href="{{ route('teachers.show', $teacher) }}">Limpiar</a>
        </div>
    </form>
</div>

<div class="detail-grid">
    <div class="card">
        <h2 class="section-title">Datos del profesor</h2>
        <div class="detail-list">
            <div><strong>Nombre:</strong> {{ $teacher->full_name }}</div>
            <div><strong>Documento:</strong> {{ $teacher->document_id ?: 'N/D' }}</div>
            <div><strong>Email:</strong> {{ $teacher->email ?: 'N/D' }}</div>
            <div><strong>Teléfono:</strong> {{ $teacher->phone ?: 'N/D' }}</div>
            <div><strong>Campus:</strong> {{ $teacher->campus?->name ?? 'N/D' }}</div>
            <div><strong>Cursos activos:</strong> {{ $summary['active_courses'] }}</div>
            <div><strong>Volumen financiero ligado:</strong> {{ \App\Support\MoneyFormat::usd($summary['finance_total']) }}</div>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Lectura operativa</h2>
        <div class="detail-list">
            <div><strong>Carga docente:</strong> {{ $summary['courses_total'] }} cursos</div>
            <div><strong>Base de alumnos:</strong> {{ $summary['students_total'] }} estudiantes únicos</div>
            <div><strong>Sesiones planificadas:</strong> {{ $summary['planned_sessions'] }}</div>
            <div><strong>Sesiones registradas:</strong> {{ $summary['completed_sessions'] }}</div>
            <div><strong>Asistencia promedio:</strong> {{ is_null($summary['attendance_rate']) ? 'N/D' : $summary['attendance_rate'].'%' }}</div>
            <div><strong>Estado general:</strong> {{ $teacher->status === 'active' ? 'Operativo' : 'Inactivo' }}</div>
        </div>
    </div>
</div>

<div class="card table-card" id="calendario">
    <div class="section-head">
        <div>
            <h2 class="section-title">Calendario semanal</h2>
            <div class="entity-sub">Ocupación real del profesor para la semana del {{ $weekStart->format('d/m/Y') }} al {{ $weekEnd->format('d/m/Y') }}</div>
        </div>
        <div class="form-actions">
            <a class="btn secondary" href="{{ route('teachers.show', array_merge(['teacher' => $teacher], request()->except('week_start') + ['week_start' => $weekStart->copy()->subWeek()->toDateString()])) }}">Semana anterior</a>
            <a class="btn secondary" href="{{ route('teachers.show', array_merge(['teacher' => $teacher], request()->except('week_start') + ['week_start' => now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString()])) }}">Semana actual</a>
            <a class="btn secondary" href="{{ route('teachers.show', array_merge(['teacher' => $teacher], request()->except('week_start') + ['week_start' => $weekStart->copy()->addWeek()->toDateString()])) }}">Semana siguiente</a>
        </div>
    </div>
    @if($calendarRows->count() > 0)
        <div class="teacher-calendar-wrap">
            <table class="teacher-calendar-table">
                <thead>
                <tr>
                    <th>Horario</th>
                    @foreach($weekDays as $day)
                        <th>{{ $day['label'] }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($calendarRows as $row)
                    <tr>
                        <td class="teacher-calendar-slot">
                            <div class="table-title">{{ $row['schedule']->compact_label }}</div>
                            <div class="table-sub">{{ $row['schedule']->display_label }}</div>
                        </td>
                        @foreach($row['cells'] as $cell)
                            <td class="teacher-calendar-cell {{ $cell['occupied'] ? 'is-occupied' : ($cell['available'] ? 'is-available' : 'is-off') }}">
                                @if($cell['occupied'])
                                    <div class="teacher-calendar-badge">Ocupado</div>
                                    <div class="teacher-calendar-title">{{ $cell['session']->group?->course?->name ?? 'Curso asignado' }}</div>
                                    <div class="teacher-calendar-sub">{{ $cell['session']->planned_class_label ?: ($cell['session']->topic ?: 'Sesión planificada') }}</div>
                                    <a class="teacher-calendar-link" href="{{ route('attendance.index', ['class_session_id' => $cell['session']->id]) }}">{{ $cell['session']->canRecordAttendance() ? 'Asistencia' : 'Ver sesión' }}</a>
                                @elseif($cell['available'])
                                    <div class="teacher-calendar-badge teacher-calendar-badge--available">Disponible</div>
                                    <div class="teacher-calendar-sub">Sin curso programado</div>
                                @else
                                    <div class="teacher-calendar-sub">No aplica</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">No hay horarios activos para construir el calendario de este profesor.</div>
    @endif
</div>

<div class="card table-card" id="agenda">
    <div class="section-head">
        <h2 class="section-title">Agenda próxima</h2>
        <div class="entity-sub">{{ $upcomingSessions->count() }} sesiones próximas</div>
    </div>
    @if($upcomingSessions->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Curso</th>
                    <th>Nivel</th>
                    <th>Programa</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($upcomingSessions as $session)
                    <tr>
                        <td>{{ $session->session_date?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td>{{ ($session->starts_at && $session->ends_at) ? substr($session->starts_at, 0, 5).' - '.substr($session->ends_at, 0, 5) : 'N/D' }}</td>
                        <td>{{ $session->group?->course?->name ?? 'N/D' }}</td>
                        <td>{{ $session->group?->course?->programLevel?->name ?? $session->group?->course?->courseLevel?->name ?? 'N/D' }}</td>
                        <td>{{ $session->topic ?: 'Pendiente' }}</td>
                        <td class="table-actions">
                            <a href="{{ route('attendance.index', ['class_session_id' => $session->id]) }}">{{ $session->canRecordAttendance() ? 'Asistencia' : 'Ver sesión' }}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">No hay sesiones futuras programadas para este profesor.</div>
    @endif
</div>

<div class="card table-card" id="cursos">
    <div class="section-head">
        <h2 class="section-title">Cursos asignados</h2>
        <div class="entity-sub">{{ $courses->count() }} de {{ $allCoursesCount }} cursos visibles con el filtro actual</div>
    </div>
    @if($courses->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Curso</th>
                    <th>Nivel</th>
                    <th>Periodo</th>
                    <th>Horario</th>
                    <th>Estudiantes</th>
                    <th>Progreso</th>
                    <th>Fechas</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($courses as $course)
                    @php
                        $sessions = $course->managedGroup?->sessions ?? collect();
                        $completed = $sessions->where('attendance_records_count', '>', 0)->count();
                        $planned = $sessions->count();
                        $students = $course->managedGroup?->enrollments?->count() ?? 0;
                    @endphp
                    <tr>
                        <td>
                            <div class="table-title">{{ $course->name }}</div>
                            <div class="table-sub">{{ $course->code ?: 'Sin código' }}</div>
                        </td>
                        <td>{{ $course->programLevel ? $course->programLevel->sort_order.'/'.$course->programLevel->program_total.' · '.$course->programLevel->name : ($course->courseLevel ? $course->courseLevel->scale_position.'/'.$course->courseLevel->scale_total.' · '.$course->courseLevel->name : ($course->level?->name ?? 'N/D')) }}</td>
                        <td>{{ $course->period?->code ?? 'N/D' }}</td>
                        <td>{{ $course->scheduleTemplate?->display_label ?? 'N/D' }}</td>
                        <td>{{ $students }}</td>
                        <td>{{ $completed }} / {{ $planned }} sesiones</td>
                        <td>
                            <div>{{ $course->start_date?->format('d/m/Y') ?? 'N/D' }}</div>
                            <div class="table-sub">{{ $course->end_date?->format('d/m/Y') ?? 'N/D' }}</div>
                        </td>
                        <td class="table-actions">
                            <a href="{{ route('courses.show', $course) }}">Ver curso</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">Este profesor todavía no tiene cursos asignados.</div>
    @endif
</div>

<div class="card table-card" id="estudiantes">
    <div class="section-head">
        <h2 class="section-title">Estudiantes asignados</h2>
        <div class="entity-sub">{{ $summary['students_total'] }} estudiantes únicos</div>
    </div>
    @if($studentRows->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Curso</th>
                    <th>Email</th>
                    <th>Estatus</th>
                    <th>Progreso</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($studentRows as $row)
                    <tr>
                        <td>{{ $row['student']->full_name }}</td>
                        <td>{{ $row['course']->name }}</td>
                        <td>{{ $row['student']->email ?: 'Sin email' }}</td>
                        <td>{{ ucfirst($row['enrollment']->status) }}</td>
                        <td>{{ (int) $row['enrollment']->progress }}%</td>
                        <td class="table-actions">
                            <a href="{{ route('students.show', $row['student']) }}">Ver alumno</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">Todavía no hay estudiantes vinculados a este profesor.</div>
    @endif
</div>

<div class="card" id="auditoria">
    <div class="section-head">
        <h2 class="section-title">Auditoría reciente</h2>
        <div class="entity-sub">Cambios sobre el perfil docente</div>
    </div>
    @include('partials.ui.audit-timeline', ['auditLogs' => $auditLogs])
</div>
@endsection
