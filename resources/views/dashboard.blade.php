@extends('layouts.app')
@section('content')
<div class="card module-head">
    <div>
        <h2>Dashboard MVP</h2>
        <p class="muted">Resumen operativo académico, asistencia y financiero.</p>
    </div>
</div>

<div class="metric-grid">
    <div class="metric-card"><div class="metric-label">Alumnos</div><div class="metric-value">{{ $studentsCount }}</div></div>
    <div class="metric-card"><div class="metric-label">Profesores</div><div class="metric-value">{{ $teachersCount }}</div></div>
    <div class="metric-card"><div class="metric-label">Cursos</div><div class="metric-value">{{ $coursesCount }}</div></div>
    <div class="metric-card"><div class="metric-label">Grupos</div><div class="metric-value">{{ $groupsCount }}</div></div>
    <div class="metric-card"><div class="metric-label">CxC Pendiente</div><div class="metric-value">${{ number_format($pendingCharges,2) }}</div></div>
</div>

<div class="card">
    <h3>Alertas abiertas</h3>
    <table><thead><tr><th>Tipo</th><th>Mensaje</th><th>Fecha</th></tr></thead><tbody>
    @forelse($openAlerts as $alert)
        <tr>
            <td><span class="status-pill {{ $alert->type === 'overdue' ? 'danger' : 'warn' }}">{{ $alert->type }}</span></td>
            <td>{{ $alert->message }}</td>
            <td>{{ $alert->created_at }}</td>
        </tr>
    @empty
        <tr><td colspan="3">Sin alertas</td></tr>
    @endforelse
    </tbody></table>
</div>
@endsection
