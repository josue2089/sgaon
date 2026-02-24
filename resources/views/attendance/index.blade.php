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
        <h1 class="page-title">Asistencia</h1>
        <p class="page-subtitle">Registra la asistencia por sesión</p>
    </div>
    @if($selectedSession)
        @include('partials.ui.status-badge', ['tone' => 'info', 'text' => $selectedSession->session_date?->format('d M Y')])
    @endif
</div>

<div class="metric-grid metric-grid-5">
    @include('partials.ui.metric-card', ['tone' => 'metric-blue', 'iconName' => 'users', 'label' => 'Total', 'value' => $total])
    @include('partials.ui.metric-card', ['tone' => 'metric-green', 'iconName' => 'check', 'label' => 'Presentes', 'value' => $present])
    @include('partials.ui.metric-card', ['tone' => 'metric-red', 'iconName' => 'warning', 'label' => 'Ausentes', 'value' => $absent])
    @include('partials.ui.metric-card', ['tone' => 'metric-orange', 'iconName' => 'trend', 'label' => 'Tarde', 'value' => $late])
    @include('partials.ui.metric-card', ['tone' => 'metric-purple', 'iconName' => 'calendar', 'label' => 'Justificadas', 'value' => $justified])
</div>

<div class="card">
    <form method="GET" action="{{ route('attendance.index') }}">
        <div class="fi-filter-bar fi-filter-bar-attendance">
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
                        <div class="attendance-item-head">
                            <div class="attendance-student-name">{{ $enrollment->student->full_name }}</div>
                            <span class="entity-avatar attendance-avatar">{{ strtoupper(substr($enrollment->student->first_name, 0, 1)) }}</span>
                        </div>
                        <div class="attendance-actions">
                            <button type="button" class="tiny-btn ok" onclick="this.closest('.attendance-item').querySelector('select').value='present'">Presente</button>
                            <button type="button" class="tiny-btn no" onclick="this.closest('.attendance-item').querySelector('select').value='absent'">Ausente</button>
                            <button type="button" class="tiny-btn late" onclick="this.closest('.attendance-item').querySelector('select').value='late'">Tarde</button>
                            <button type="button" class="tiny-btn just" onclick="this.closest('.attendance-item').querySelector('select').value='justified'">Justificada</button>
                        </div>
                        <div class="attendance-field">
                            <label>Status</label>
                            <select name="records[{{ $i }}][status]">
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" @selected(($records[$enrollment->id]->status ?? 'present')==$status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="attendance-field">
                            <label>Nota</label>
                            <input name="records[{{ $i }}][notes]" value="{{ $records[$enrollment->id]->notes ?? '' }}">
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Guardar asistencia</button>
            </div>
        </form>
    </div>
@endif
@endsection
