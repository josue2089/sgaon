@extends('layouts.app')
@section('content')
@php
    $total = $groups->total();
    $withTeacher = $groups->getCollection()->filter(fn ($g) => !is_null($g->teacher_id))->count();
    $active = $groups->getCollection()->where('status', 'active')->count();
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Grupos 🧩</h1>
        <p class="page-subtitle">Programación de grupos, docentes y horarios</p>
    </div>
    <a class="btn" href="{{ route('groups.create') }}">Nuevo grupo</a>
</div>

<div class="metric-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));">
    <div class="metric-card metric-blue"><div class="metric-label">Total Grupos</div><div class="metric-value">{{ $total }}</div></div>
    <div class="metric-card metric-green"><div class="metric-label">Con Docente</div><div class="metric-value">{{ $withTeacher }}</div></div>
    <div class="metric-card metric-purple"><div class="metric-label">Activos</div><div class="metric-value">{{ $active }}</div></div>
    <div class="metric-card metric-orange"><div class="metric-label">Cobertura</div><div class="metric-value">{{ $total ? round(($active / $total) * 100) : 0 }}%</div></div>
</div>

<div class="card">
    <div class="fi-filter-bar">
        <div class="search"><input type="text" placeholder="Buscar grupo..." disabled></div>
        <select style="max-width:220px;" disabled><option>Todos los cursos</option></select>
        <button class="btn secondary" type="button">Filtros</button>
    </div>
</div>

<div class="entity-grid">
    @foreach($groups as $group)
        <div class="entity-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:60px;height:60px;border-radius:18px;background:linear-gradient(135deg,#3b82f6,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.9rem;">👥</div>
                <span class="badge-pill {{ $group->status === 'active' ? 'badge-ok' : 'badge-warn' }}">{{ $group->status }}</span>
            </div>
            <div class="entity-title">{{ $group->name }}</div>
            <div class="entity-sub">{{ $group->course->name ?? 'Sin curso' }}</div>
            <div class="entity-sub">{{ $group->teacher->full_name ?? 'Sin asignar' }}</div>
            <div class="entity-sub">{{ $group->schedule ?: 'Horario pendiente' }}</div>
            <div style="margin-top:.85rem; display:flex; justify-content:flex-end;">
                <a href="{{ route('groups.edit',$group) }}">Editar</a>
            </div>
        </div>
    @endforeach
</div>

@if($groups->hasPages())
    <div class="card">{{ $groups->links() }}</div>
@endif
@endsection
