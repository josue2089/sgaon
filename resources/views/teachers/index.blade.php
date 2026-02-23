@extends('layouts.app')
@section('content')
@php
    $total = $teachers->total();
    $active = $teachers->getCollection()->where('status', 'active')->count();
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Profesores 👨‍🏫</h1>
        <p class="page-subtitle">Gestiona tu equipo docente</p>
    </div>
    <a class="btn" href="{{ route('teachers.create') }}">Nuevo profesor</a>
</div>

<div class="metric-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));">
    <div class="metric-card metric-purple"><div class="metric-icon">👨‍🏫</div><div class="metric-label">Total Profesores</div><div class="metric-value">{{ $total }}</div></div>
    <div class="metric-card metric-blue"><div class="metric-icon">👥</div><div class="metric-label">Total Estudiantes</div><div class="metric-value">270</div></div>
    <div class="metric-card metric-orange"><div class="metric-icon">⭐</div><div class="metric-label">Rating Promedio</div><div class="metric-value">4.9</div></div>
    <div class="metric-card metric-green"><div class="metric-icon">📚</div><div class="metric-label">Docentes Activos</div><div class="metric-value">{{ $active }}</div></div>
</div>

<div class="card">
    <div class="fi-filter-bar">
        <div class="search"><input type="text" placeholder="Buscar profesor..." disabled></div>
        <select style="max-width:320px;" disabled><option>Todas las especialidades</option></select>
        <button class="btn secondary" type="button">Filtros</button>
    </div>
</div>

<div class="entity-grid">
    @foreach($teachers as $teacher)
        <div class="entity-card entity-card--airy">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <span class="entity-avatar">{{ strtoupper(substr($teacher->first_name, 0, 1)) }}</span>
                <span class="badge-pill badge-info">⭐ 4.9</span>
            </div>
            <div class="entity-spacer"></div>
            <div class="entity-title">Prof. {{ $teacher->full_name }}</div>
            <div class="entity-sub">{{ $teacher->email ?: 'Sin email' }}</div>
            <div class="entity-sub">{{ $teacher->phone ?: 'Sin teléfono' }}</div>
            <div style="margin-top:.85rem; display:flex; justify-content:space-between; align-items:center;">
                <span class="badge-pill {{ $teacher->status === 'active' ? 'badge-ok' : 'badge-warn' }}">{{ $teacher->status }}</span>
                <a href="{{ route('teachers.edit', $teacher) }}">Editar</a>
            </div>
        </div>
    @endforeach
</div>

@if($teachers->hasPages())
    <div class="card">{{ $teachers->links() }}</div>
@endif
@endsection
