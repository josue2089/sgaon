@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Cursos</h1>
        <p class="page-subtitle">Panel operativo de cursos, planificación y avance académico</p>
    </div>
    <a class="btn" href="{{ route('courses.create') }}">Nuevo Curso</a>
</div>

<div class="metric-grid metric-grid-4">
    @include('partials.ui.metric-card', ['tone' => 'metric-blue', 'iconName' => 'book', 'label' => 'Total Cursos', 'value' => $stats['courses']])
    @include('partials.ui.metric-card', ['tone' => 'metric-green', 'iconName' => 'users', 'label' => 'Total Estudiantes', 'value' => $stats['students']])
    @include('partials.ui.metric-card', ['tone' => 'metric-purple', 'iconName' => 'calendar', 'label' => 'Horas Académicas', 'value' => is_null($stats['planned_hours']) ? 'N/D' : $stats['planned_hours'].' h']) 
    @include('partials.ui.metric-card', ['tone' => 'metric-orange', 'iconName' => 'award', 'label' => 'Niveles CEFR', 'value' => $levelStats->count()])
</div>

<form method="GET" action="{{ route('courses.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar curso o código...">
        </div>
        <select name="level">
            <option value="">Todos los niveles</option>
            @foreach($levels as $level)
                <option value="{{ $level->code }}" @selected($filters['level'] === $level->code)>{{ $level->code }} - {{ $level->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Todos</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
        </select>
        {{-- #450 --}}
        <select name="teacher_id" aria-label="Profesor">
            <option value="">Todos los profesores</option>
            @foreach($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === (string) $teacher->id)>{{ $teacher->full_name }}</option>
            @endforeach
        </select>
        <select name="period_id" aria-label="Período">
            <option value="">Todos los períodos</option>
            @foreach($periods->unique('code') as $period)
                <option value="{{ $period->id }}" @selected($filters['period_id'] === (string) $period->id)>{{ $period->code }}</option>
            @endforeach
        </select>
        <select name="program_id" aria-label="Programa">
            <option value="">Todos los programas</option>
            @foreach($programs as $program)
                <option value="{{ $program->id }}" @selected($filters['program_id'] === (string) $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
        <label class="fi-check">
            <input type="checkbox" name="ending_soon" value="1" @checked($filters['ending_soon'])>
            Termina pronto (14 días)
        </label>
        <button class="btn secondary" type="submit">Filtros</button>
        @if(collect($filters)->filter()->isNotEmpty())
            <a class="btn secondary" href="{{ route('courses.index') }}">Quitar filtros</a>
        @endif
    </div>
</form>

@if($courses->count() === 0)
    <div class="card empty-state">No hay cursos para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th scope="col">Curso</th>
                    <th scope="col">Profesor</th>
                    <th scope="col">Horario</th>
                    <th scope="col">Período</th>
                    <th scope="col">Inicio</th>
                    <th scope="col">Fin</th>
                    <th scope="col">Estudiantes</th>
                    <th scope="col">Sesiones</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="sr-only">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach($courses as $course)
                    @php
                        $groupId = $course->managed_group_id;
                        $studentsCount = $groupId ? (int) ($studentsByGroup[$groupId] ?? 0) : 0;
                        $completedCount = $groupId ? (int) ($completedSessionsByGroup[$groupId] ?? 0) : 0;
                        $plannedCount = $groupId ? (int) ($plannedSessionsByGroup[$groupId] ?? 0) : 0;
                    @endphp
                    <tr>
                        <td>
                            <div class="table-title">{{ $course->name }}</div>
                            <div class="table-sub">{{ $course->code ?: 'Sin código' }}</div>
                        </td>
                        <td>{{ $course->teacher?->full_name ?? 'Sin asignar' }}</td>
                        <td>{{ $course->scheduleTemplate?->display_label ?? 'Sin horario' }}</td>
                        <td>{{ $course->period?->code ?? 'Sin período' }}</td>
                        <td>{{ $course->start_date?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td>{{ $course->end_date?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td>{{ $studentsCount }}</td>
                        <td>{{ $completedCount }}/{{ $plannedCount }}</td>
                        <td>@include('partials.ui.status', ['status' => $course->status])</td>
                        <td class="table-actions">
                            <a href="{{ route('courses.show', $course) }}">Detalle</a>
                            <a href="{{ route('courses.edit', $course) }}">Editar</a>
                            @if(auth()->user()?->isMasterAdmin())
                                <form method="POST" action="{{ route('courses.destroy', $course) }}" class="inline-form"
                                      data-confirm-title="Eliminar curso" data-confirm-ok="Eliminar permanentemente" data-confirm-danger
                                      data-confirm="Vas a eliminar permanentemente el curso {{ $course->name }} con su grupo, sesiones, asistencia, inscripciones y evaluaciones. Los alumnos no se eliminan, solo su vínculo con este curso. Esta acción no se puede deshacer.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-link-danger">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($courses->hasPages())
    <div class="card">{{ $courses->links() }}</div>
@endif

@endsection
