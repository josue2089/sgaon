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

<div class="card">{{ $enrollments->links() }}</div>
@endsection
