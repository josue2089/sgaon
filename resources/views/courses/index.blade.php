@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Cursos 📚</h1>
        <p class="page-subtitle">Gestiona niveles y grupos</p>
    </div>
    <a class="btn" href="{{ route('courses.create') }}">Nuevo Curso</a>
</div>

<div class="metric-grid metric-grid-4">
    @include('partials.ui.metric-card', ['tone' => 'metric-blue', 'icon' => '📘', 'label' => 'Total Cursos', 'value' => $stats['courses']])
    @include('partials.ui.metric-card', ['tone' => 'metric-green', 'icon' => '👥', 'label' => 'Total Estudiantes', 'value' => $stats['students']])
    @include('partials.ui.metric-card', ['tone' => 'metric-purple', 'icon' => '📈', 'label' => 'Ocupación', 'value' => is_null($stats['occupancy']) ? 'N/D' : $stats['occupancy'].'%', 'subtitle' => is_null($stats['occupancy']) ? 'Sin capacidad definida' : null])
    @include('partials.ui.metric-card', ['tone' => 'metric-orange', 'icon' => '🏅', 'label' => 'Niveles CEFR', 'value' => $levelStats->count()])
</div>

<div class="card">
    <h2 class="section-title">Niveles CEFR 🎓</h2>
    <div class="cefr-grid">
        @foreach($levelStats as $level)
            @php
                $code = strtoupper($level->code ?: substr((string) $level->name, 0, 2));
                $label = trim(preg_replace('/^[A-Z0-9\\- ]+/i', '', (string) $level->name)) ?: $level->name;
            @endphp
            <div class="cefr-card">
                <div class="cefr-badge">{{ $code }}</div>
                <div class="cefr-label">{{ $label }}</div>
                <div class="cefr-value">{{ $level->courses_count }}</div>
            </div>
        @endforeach
    </div>
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
        <button class="btn secondary" type="submit">Filtros</button>
    </div>
</form>

@if($courses->count() === 0)
    <div class="card empty-state">No hay cursos para los filtros seleccionados.</div>
@else
    <div class="entity-grid">
        @foreach($courses as $course)
            <div class="entity-card entity-card--airy">
                <div class="entity-card-top">
                    <div class="entity-icon">📘</div>
                    @include('partials.ui.status-badge', ['tone' => 'level', 'text' => strtoupper($course->level->code ?? substr((string) ($course->level->name ?? 'N/A'), 0, 2))])
                </div>
                <div class="entity-spacer"></div>
                <div class="entity-title">{{ $course->name }}</div>
                <div class="entity-sub">{{ $course->code ?: 'Sin código' }}</div>
                <div class="entity-sub">{{ $course->campus->name ?? 'Sin sede' }}</div>
                <div class="entity-bottom">
                    @include('partials.ui.status-badge', ['tone' => $course->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($course->status)])
                    <a href="{{ route('courses.edit',$course) }}">Editar</a>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if($courses->hasPages())
    <div class="card">{{ $courses->links() }}</div>
@endif
@endsection
