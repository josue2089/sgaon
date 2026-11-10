@extends('layouts.app')
@section('content')
{{-- Bitácora File A/B del alumno en un curso. #470 --}}
<div class="module-head">
    <div>
        <h1 class="page-title">Bitácora · {{ $title }}</h1>
        <p class="page-subtitle">{{ $student?->full_name }} · {{ $levelName }}{{ $teacher ? ' · '.$teacher : '' }}</p>
    </div>
    <div class="form-actions">
        <a class="btn" href="{{ $pdfUrl }}">Descargar PDF</a>
        <a class="btn secondary" href="{{ $backUrl }}">Volver</a>
    </div>
</div>

<div class="summary-grid">
    <div class="card summary-card"><div class="summary-label">Clases del curso</div><div class="summary-value">{{ $summary['total'] }}</div></div>
    <div class="card summary-card"><div class="summary-label">Asistencias</div><div class="summary-value">{{ $summary['present'] }}</div></div>
    <div class="card summary-card"><div class="summary-label">Inasistencias</div><div class="summary-value">{{ $summary['absent'] }}</div></div>
    <div class="card summary-card"><div class="summary-label">Horario</div><div class="table-sub">{{ $schedule ?? 'N/D' }}</div></div>
</div>

<div class="card table-card">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>#</th><th>Fecha</th><th>Unidad / actividad</th><th>Tipo</th><th>Asistencia</th></tr></thead>
            <tbody>
            @forelse($sessions as $session)
                <tr>
                    <td>{{ $session['number'] }}</td>
                    <td>{{ $session['date']?->format('d/m/Y') }}</td>
                    <td>
                        <div class="table-title">{{ $session['unit'] }}</div>
                        @if($session['activity'])<div class="table-sub">{{ $session['activity'] }}</div>@endif
                        @if($session['topic'])<div class="table-sub">Visto: {{ $session['topic'] }}</div>@endif
                    </td>
                    <td>{{ $session['type'] ?? '—' }}</td>
                    <td>@if($session['attendance_status'])@include('partials.ui.status', ['status' => $session['attendance_status'], 'domain' => 'attendance'])@else<span class="table-sub">—</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-inline">El curso todavía no tiene clases planificadas.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($makeups->isNotEmpty())
    <div class="card table-card">
        <h2 class="section-title section-title-sm">Recuperativas</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Contenido</th><th>Fecha</th><th>Profesor</th></tr></thead>
                <tbody>
                @foreach($makeups as $row)
                    <tr><td>{{ $row['content'] }}</td><td>{{ $row['date']?->format('d/m/Y') ?? 'N/D' }}</td><td>{{ $row['teacher'] ?? 'N/D' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
