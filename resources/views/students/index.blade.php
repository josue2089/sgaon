@extends('layouts.app')
@section('content')
@php
    $total = $students->total();
    $active = $students->getCollection()->where('status', 'active')->count();
    $inactive = $students->getCollection()->where('status', '!=', 'active')->count();
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Alumnos 👨‍🎓</h1>
        <p class="page-subtitle">Gestiona todos los estudiantes</p>
    </div>
    <a class="btn" href="{{ route('students.create') }}">Nuevo alumno</a>
</div>

<div class="soft-kpi-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));">
    <div class="soft-kpi"><div class="label">Total Alumnos</div><div class="value">{{ $total }}</div></div>
    <div class="soft-kpi"><div class="label">Activos</div><div class="value" style="color:#16a34a;">{{ $active }}</div></div>
    <div class="soft-kpi"><div class="label">Inactivos</div><div class="value" style="color:#dc2626;">{{ $inactive }}</div></div>
    <div class="soft-kpi"><div class="label">Asist. Promedio</div><div class="value" style="color:#7c3aed;">87%</div></div>
</div>

<div class="card">
    <div class="fi-filter-bar">
        <div class="search"><input type="text" placeholder="Buscar por nombre o email..." disabled></div>
        <select style="max-width:160px;" disabled><option>Todos</option></select>
        <button class="btn secondary" type="button">Filtros</button>
    </div>
</div>

<div class="entity-grid">
    @foreach($students as $student)
        @php
            $level = optional(optional($student->enrollments->first())->group)->course?->level?->name;
        @endphp
        <div class="entity-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <span class="entity-avatar">{{ strtoupper(substr($student->first_name, 0, 1)) }}</span>
                <span class="badge-pill badge-level">{{ $level ? strtoupper(substr($level, 0, 2)) : 'N/A' }}</span>
            </div>
            <div class="entity-title">{{ $student->full_name }}</div>
            <div class="entity-sub">{{ $student->email ?: 'Sin email' }}</div>
            <div class="entity-sub">{{ $student->campus->name ?? 'Sin sede' }}</div>
            <div style="margin-top:.8rem; display:flex; justify-content:space-between; align-items:center;">
                <span class="badge-pill {{ $student->status === 'active' ? 'badge-ok' : 'badge-warn' }}">{{ $student->status }}</span>
                <a href="{{ route('students.edit', $student) }}">Editar</a>
            </div>
        </div>
    @endforeach
</div>

<div class="card">{{ $students->links() }}</div>
@endsection
