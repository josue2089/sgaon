@extends('layouts.app')
@section('content')
@php
    $total = $enrollments->total();
    $active = $enrollments->getCollection()->where('status', 'active')->count();
    $completed = $enrollments->getCollection()->where('status', 'completed')->count();
    $avgProgress = (int) round($enrollments->getCollection()->avg('progress') ?? 0);
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Inscripciones 📝</h1>
        <p class="page-subtitle">Control de alumnos inscritos por grupo</p>
    </div>
    <a class="btn" href="{{ route('enrollments.create') }}">Nueva inscripción</a>
</div>

<div class="metric-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));">
    <div class="metric-card metric-blue"><div class="metric-label">Total</div><div class="metric-value">{{ $total }}</div></div>
    <div class="metric-card metric-green"><div class="metric-label">Activas</div><div class="metric-value">{{ $active }}</div></div>
    <div class="metric-card metric-purple"><div class="metric-label">Completadas</div><div class="metric-value">{{ $completed }}</div></div>
    <div class="metric-card metric-orange"><div class="metric-label">Progreso Prom.</div><div class="metric-value">{{ $avgProgress }}%</div></div>
</div>

<form method="GET" action="{{ route('enrollments.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar alumno (nombre, cédula, representante), grupo o curso...">
        </div>
        <select name="group_id" style="max-width:260px;">
            <option value="">Todos los grupos</option>
            @foreach($groups as $group)
                <option value="{{ $group->id }}" @selected((string) $filters['group_id'] === (string) $group->id)>{{ $group->name }}{{ $group->course ? ' · '.$group->course->name : '' }}</option>
            @endforeach
        </select>
        <select name="status" style="max-width:200px;">
            <option value="">Todos los estados</option>
            <option value="active" @selected($filters['status'] === 'active')>Activa</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactiva</option>
            <option value="completed" @selected($filters['status'] === 'completed')>Completada</option>
            <option value="withdrawn" @selected($filters['status'] === 'withdrawn')>Retirada</option>
        </select>
        <button class="btn secondary" type="submit">Filtros</button>
    </div>
</form>

@if($enrollments->count() === 0)
    <div class="card empty-state">No hay inscripciones para los filtros seleccionados.</div>
@else
    <div class="entity-grid">
        @foreach($enrollments as $enrollment)
            <div class="entity-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                    <div style="width:60px;height:60px;border-radius:18px;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.9rem;">📘</div>
                    <span class="badge-pill {{ $enrollment->status === 'active' ? 'badge-ok' : ($enrollment->status === 'completed' ? 'badge-info' : 'badge-warn') }}">{{ $enrollment->status }}</span>
                </div>
                <div class="entity-title">{{ $enrollment->student->full_name ?? 'Sin alumno' }}</div>
                <div class="entity-sub">{{ $enrollment->group->name ?? 'Sin grupo' }}</div>
                <div style="margin-top:.65rem;">
                    <div class="entity-sub">Progreso: {{ (int) $enrollment->progress }}%</div>
                    <div style="height:8px;background:#e5edff;border-radius:999px;margin-top:.3rem;overflow:hidden;">
                        <div style="height:100%;width:{{ max(0, min(100, (int) $enrollment->progress)) }}%;background:linear-gradient(90deg,#22c55e,#16a34a);"></div>
                    </div>
                </div>
                <div style="margin-top:.8rem; display:flex; justify-content:flex-end;">
                    <a href="{{ route('enrollments.edit',$enrollment) }}">Editar</a>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if($enrollments->hasPages())
    <div class="card">{{ $enrollments->links() }}</div>
@endif
@endsection
