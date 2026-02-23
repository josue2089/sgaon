@extends('layouts.app')
@section('content')
<div class="card"><h1>Dashboard MVP</h1><p class="muted">Resumen operativo académico + asistencia + financiero.</p></div>
<div class="row">
    <div class="card kpi"><strong>Alumnos</strong><div>{{ $studentsCount }}</div></div>
    <div class="card kpi"><strong>Profesores</strong><div>{{ $teachersCount }}</div></div>
    <div class="card kpi"><strong>Cursos</strong><div>{{ $coursesCount }}</div></div>
    <div class="card kpi"><strong>Grupos</strong><div>{{ $groupsCount }}</div></div>
    <div class="card kpi"><strong>CxC Pendiente</strong><div>${{ number_format($pendingCharges,2) }}</div></div>
</div>
<div class="card">
    <h3>Alertas abiertas</h3>
    <table><thead><tr><th>Tipo</th><th>Mensaje</th><th>Fecha</th></tr></thead><tbody>
    @forelse($openAlerts as $alert)
        <tr><td>{{ $alert->type }}</td><td>{{ $alert->message }}</td><td>{{ $alert->created_at }}</td></tr>
    @empty
        <tr><td colspan="3">Sin alertas</td></tr>
    @endforelse
    </tbody></table>
</div>
@endsection
