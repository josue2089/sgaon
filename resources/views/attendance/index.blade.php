@extends('layouts.app')
@section('content')
@php
    $present = $records->where('status', 'present')->count();
    $absent = $records->where('status', 'absent')->count();
    $late = $records->where('status', 'late')->count();
    $justified = $records->where('status', 'justified')->count();
    $total = $enrollments->count();
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Asistencia ✓</h1>
        <p class="page-subtitle">Registra la asistencia por sesión</p>
    </div>
    @if($selectedSession)
        <span class="badge-pill badge-info">{{ $selectedSession->session_date?->format('d M Y') }}</span>
    @endif
</div>

<div class="metric-grid" style="grid-template-columns:repeat(5,minmax(0,1fr));">
    <div class="metric-card metric-blue"><div class="metric-label">Total</div><div class="metric-value">{{ $total }}</div></div>
    <div class="metric-card metric-green"><div class="metric-label">Presentes</div><div class="metric-value">{{ $present }}</div></div>
    <div class="metric-card" style="background:linear-gradient(135deg,#ef4444,#b91c1c);"><div class="metric-label">Ausentes</div><div class="metric-value">{{ $absent }}</div></div>
    <div class="metric-card metric-orange"><div class="metric-label">Tarde</div><div class="metric-value">{{ $late }}</div></div>
    <div class="metric-card metric-purple"><div class="metric-label">Justificadas</div><div class="metric-value">{{ $justified }}</div></div>
</div>

<div class="card">
    <form method="GET" action="{{ route('attendance.index') }}">
        <div class="fi-filter-bar">
            <div class="search">
                <select name="class_session_id">
                    <option value="">Seleccione sesión</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}" @selected(request('class_session_id')==$session->id)>{{ $session->session_date?->format('Y-m-d') }} - {{ $session->group->name ?? '' }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn" type="submit">Cargar sesión</button>
        </div>
    </form>
</div>

@if($selectedSession)
<div class="card">
    <form method="POST" action="{{ route('attendance.store') }}">
        @csrf
        <input type="hidden" name="class_session_id" value="{{ $selectedSession->id }}">
        <div class="attendance-grid">
            @foreach($enrollments as $i => $enrollment)
                <div class="attendance-item">
                    <input type="hidden" name="records[{{ $i }}][enrollment_id]" value="{{ $enrollment->id }}">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:.6rem;">
                        <div style="font-size:1.35rem; font-weight:900; color:#0a1e5e;">{{ $enrollment->student->full_name }}</div>
                        <span class="entity-avatar" style="width:2.8rem;height:2.8rem;font-size:1rem;">{{ strtoupper(substr($enrollment->student->first_name, 0, 1)) }}</span>
                    </div>
                    <div class="attendance-actions">
                        <button type="button" class="tiny-btn ok" onclick="this.closest('.attendance-item').querySelector('select').value='present'">Presente</button>
                        <button type="button" class="tiny-btn no" onclick="this.closest('.attendance-item').querySelector('select').value='absent'">Ausente</button>
                        <button type="button" class="tiny-btn late" onclick="this.closest('.attendance-item').querySelector('select').value='late'">Tarde</button>
                        <button type="button" class="tiny-btn just" onclick="this.closest('.attendance-item').querySelector('select').value='justified'">Justificada</button>
                    </div>
                    <div style="margin-top:.6rem;">
                        <label>Status</label>
                        <select name="records[{{ $i }}][status]">@foreach($statuses as $status)<option value="{{ $status }}" @selected(($records[$enrollment->id]->status ?? 'present')==$status)>{{ $status }}</option>@endforeach</select>
                    </div>
                    <div style="margin-top:.45rem;">
                        <label>Nota</label>
                        <input name="records[{{ $i }}][notes]" value="{{ $records[$enrollment->id]->notes ?? '' }}">
                    </div>
                </div>
            @endforeach
        </div>
        <div class="form-actions"><button class="btn" type="submit">Guardar asistencia</button></div>
    </form>
</div>
@endif
@endsection
