@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">🏫 {{ $campus->name }}</h1>
        <p class="page-subtitle">
            {{ $campus->code }}
            @if(collect([$campus->city, $campus->state, $campus->country])->filter()->isNotEmpty())
                · {{ collect([$campus->city, $campus->state, $campus->country])->filter()->implode(', ') }}
            @endif
        </p>
    </div>
    <div>
        <a class="btn secondary" href="{{ route('campuses.index') }}">Volver</a>
        <a class="btn" href="{{ route('campuses.edit', $campus) }}">Editar</a>
    </div>
</div>

<div class="metric-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));">
    <div class="metric-card metric-blue">
        <div class="metric-label">Alumnos totales</div>
        <div class="metric-value">{{ $stats['students_total'] }}</div>
        <div class="metric-hint">{{ $stats['students_active'] }} activos · {{ $stats['students_inactive'] }} inactivos</div>
    </div>
    <div class="metric-card metric-green">
        <div class="metric-label">Grupos</div>
        <div class="metric-value">{{ $stats['groups_total'] }}</div>
        <div class="metric-hint">{{ $stats['groups_active'] }} activos</div>
    </div>
    <div class="metric-card metric-purple">
        <div class="metric-label">Docentes</div>
        <div class="metric-value">{{ $stats['teachers_total'] }}</div>
    </div>
    <div class="metric-card metric-orange">
        <div class="metric-label">Cursos</div>
        <div class="metric-value">{{ $stats['courses_total'] }}</div>
    </div>
</div>

<div class="metric-grid" style="grid-template-columns:repeat(3,minmax(0,1fr)); margin-top:1rem;">
    <div class="metric-card">
        <div class="metric-label">Inscripciones activas</div>
        <div class="metric-value">{{ $stats['enrollments_active'] }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Usuarios internos</div>
        <div class="metric-value">{{ $stats['users_total'] }}</div>
        @if($usersByRole->isNotEmpty())
            <div class="metric-hint">
                @foreach($usersByRole as $role => $count)
                    {{ $role }}: {{ $count }}@if(! $loop->last), @endif
                @endforeach
            </div>
        @endif
    </div>
    <div class="metric-card">
        <div class="metric-label">Estado</div>
        <div class="metric-value">@include('partials.ui.status-badge', ['tone' => $campus->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($campus->status)])</div>
    </div>
</div>

<div class="card" style="margin-top:1.25rem;">
    <div class="section-head">
        <h2 class="section-title">Accesos rápidos</h2>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('students.index', ['campus_id' => $campus->id]) }}">Ver alumnos de esta sede</a>
        <a class="btn secondary" href="{{ route('groups.index', ['campus_id' => $campus->id]) }}">Ver grupos de esta sede</a>
    </div>
</div>
@endsection
