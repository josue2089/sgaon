@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Cursos 📚</h1>
        <p class="page-subtitle">Gestiona niveles y grupos</p>
    </div>
    <a class="btn" href="{{ route('courses.create') }}">Nuevo curso</a>
</div>

<div class="metric-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));">
    <div class="metric-card metric-blue"><div class="metric-label">Total Cursos</div><div class="metric-value">{{ $stats['courses'] }}</div></div>
    <div class="metric-card metric-green"><div class="metric-label">Total Estudiantes</div><div class="metric-value">{{ $stats['students'] }}</div></div>
    <div class="metric-card metric-purple"><div class="metric-label">Ocupación</div><div class="metric-value">{{ $stats['occupancy'] }}%</div></div>
    <div class="metric-card metric-orange"><div class="metric-label">Niveles CEFR</div><div class="metric-value">{{ $levelStats->count() }}</div></div>
</div>

<div class="card">
    <h2 style="font-size:2.25rem; color:#0a1e5e; margin-bottom:1rem;">Niveles CEFR 🎓</h2>
    <div class="entity-grid" style="grid-template-columns:repeat(6,minmax(0,1fr));">
        @foreach($levelStats as $level)
            @php
                $code = strtoupper($level->code ?: substr((string) $level->name, 0, 2));
                $label = trim(preg_replace('/^[A-Z0-9\\- ]+/i', '', (string) $level->name)) ?: $level->name;
            @endphp
            <div class="entity-card" style="text-align:center;">
                <div style="width:64px;height:64px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);margin:0 auto;display:flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;font-weight:900;">{{ $code }}</div>
                <div style="margin-top:.85rem;font-size:1.5rem;font-weight:900;color:#0a1e5e;">{{ $label }}</div>
                <div style="margin-top:.4rem;font-size:2.25rem;font-weight:900;color:#5b43dc;">{{ $level->courses_count }}</div>
            </div>
        @endforeach
    </div>
</div>

<div class="card">
    <div class="fi-filter-bar">
        <div class="search"><input type="text" placeholder="Buscar curso o código..." disabled></div>
        <select style="max-width:200px;" disabled><option>Todos los niveles</option></select>
        <select style="max-width:180px;" disabled><option>Todos</option></select>
        <button class="btn secondary" type="button">Filtros</button>
    </div>
</div>

<div class="entity-grid">
    @foreach($courses as $course)
        <div class="entity-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:62px;height:62px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#2563eb);display:flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;">📘</div>
                <span class="badge-pill badge-level">{{ strtoupper($course->level->code ?? substr((string) ($course->level->name ?? 'N/A'), 0, 2)) }}</span>
            </div>
            <div class="entity-title">{{ $course->name }}</div>
            <div class="entity-sub">{{ $course->code ?: 'Sin código' }}</div>
            <div class="entity-sub">{{ $course->campus->name ?? 'Sin sede' }}</div>
            <div style="margin-top:.85rem; display:flex; justify-content:space-between; align-items:center;">
                <span class="badge-pill {{ $course->status === 'active' ? 'badge-ok' : 'badge-warn' }}">{{ $course->status }}</span>
                <a href="{{ route('courses.edit',$course) }}">Editar</a>
            </div>
        </div>
    @endforeach
</div>

<div class="card">{{ $courses->links() }}</div>
@endsection
