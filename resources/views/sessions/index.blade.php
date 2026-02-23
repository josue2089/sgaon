@extends('layouts.app')
@section('content')
@php
    $total = $sessions->total();
    $today = $sessions->getCollection()->filter(fn ($s) => optional($s->session_date)?->isToday())->count();
    $week = $sessions->getCollection()->filter(fn ($s) => optional($s->session_date)?->isCurrentWeek())->count();
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Sesiones 🗓️</h1>
        <p class="page-subtitle">Agenda de clases por grupo</p>
    </div>
    <a class="btn" href="{{ route('sessions.create') }}">Nueva sesión</a>
</div>

<div class="soft-kpi-grid" style="grid-template-columns:repeat(3,minmax(0,1fr));">
    <div class="soft-kpi"><div class="label">Total Sesiones</div><div class="value">{{ $total }}</div></div>
    <div class="soft-kpi"><div class="label">Hoy</div><div class="value" style="color:#2563eb;">{{ $today }}</div></div>
    <div class="soft-kpi"><div class="label">Esta Semana</div><div class="value" style="color:#7c3aed;">{{ $week }}</div></div>
</div>

<div class="entity-grid">
    @foreach($sessions as $session)
        <div class="entity-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="width:58px;height:58px;border-radius:18px;background:linear-gradient(135deg,#3b82f6,#0ea5e9);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.8rem;">🕒</div>
                <span class="badge-pill badge-info">{{ $session->session_date?->format('d M') }}</span>
            </div>
            <div class="entity-title">{{ $session->group->name ?? 'Sin grupo' }}</div>
            <div class="entity-sub">{{ $session->topic ?: 'Sin tema' }}</div>
            <div class="entity-sub">Fecha: {{ $session->session_date?->format('Y-m-d') }}</div>
            <div style="margin-top:.8rem; display:flex; justify-content:flex-end;">
                <a href="{{ route('sessions.edit',$session) }}">Editar</a>
            </div>
        </div>
    @endforeach
</div>

<div class="card">{{ $sessions->links() }}</div>
@endsection
