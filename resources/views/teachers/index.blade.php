@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Profesores</h1>
        <p class="page-subtitle">Gestiona tu equipo docente</p>
    </div>
    <a class="btn" href="{{ route('teachers.create') }}">Nuevo Profesor</a>
</div>

<div class="metric-grid metric-grid-4">
    @include('partials.ui.metric-card', ['tone' => 'metric-purple', 'iconName' => 'users', 'label' => 'Total Profesores', 'value' => $summary['total']])
    @include('partials.ui.metric-card', ['tone' => 'metric-blue', 'iconName' => 'teacher', 'label' => 'Total Estudiantes', 'value' => $summary['students_total']])
    @include('partials.ui.metric-card', ['tone' => 'metric-orange', 'iconName' => 'award', 'label' => 'Rating Promedio', 'value' => 'N/D', 'subtitle' => 'Sin módulo de evaluación'])
    @include('partials.ui.metric-card', ['tone' => 'metric-green', 'iconName' => 'book', 'label' => 'Docentes Activos', 'value' => $summary['active']])
</div>

<form method="GET" action="{{ route('teachers.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar profesor...">
        </div>
        <select name="specialty">
            <option value="">Todas las especialidades</option>
            @foreach($specialties as $specialty)
                <option value="{{ $specialty }}" @selected($filters['specialty'] === $specialty)>{{ $specialty }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Todos los estados</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
        </select>
        <button class="btn secondary" type="submit">Filtros</button>
    </div>
</form>

@if($teachers->count() === 0)
    <div class="card empty-state">No hay profesores para los filtros seleccionados.</div>
@else
    <div class="entity-grid">
        @foreach($teachers as $teacher)
            <div class="entity-card entity-card--airy">
                <div class="entity-card-top">
                    <span class="entity-avatar">
                        @if($teacher->profile_photo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($teacher->profile_photo_path) }}" alt="{{ $teacher->full_name }}">
                        @else
                            {{ strtoupper(substr($teacher->first_name, 0, 1)) }}
                        @endif
                    </span>
                    @include('partials.ui.status-badge', ['tone' => 'info', 'text' => 'N/D'])
                </div>
                <div class="entity-spacer"></div>
                <div class="entity-title">Prof. {{ $teacher->full_name }}</div>
                <div class="entity-sub">{{ $teacher->email ?: 'Sin email' }}</div>
                <div class="entity-sub">{{ $teacher->phone ?: 'Sin teléfono' }}</div>
                <div class="entity-sub">{{ (int) ($studentsByTeacher[$teacher->id] ?? 0) }} estudiantes asignados</div>
                <div class="entity-bottom">
                    @include('partials.ui.status-badge', ['tone' => $teacher->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($teacher->status)])
                    <a href="{{ route('teachers.edit', $teacher) }}">Editar</a>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if($teachers->hasPages())
    <div class="card">{{ $teachers->links() }}</div>
@endif
@endsection
