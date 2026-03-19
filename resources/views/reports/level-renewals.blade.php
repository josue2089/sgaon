@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Renovación de niveles</h1>
        <p class="page-subtitle">Seguimiento de recordatorios enviados para reinscripción al siguiente nivel</p>
    </div>
</div>

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'mail', 'label' => 'Total recordatorios', 'value' => $summary['total']])
    @include('partials.ui.soft-kpi', ['iconName' => 'check', 'label' => 'Emails enviados', 'value' => $summary['sent'], 'valueClass' => 'value-ok'])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Pendientes email', 'value' => $summary['pending'], 'valueClass' => 'value-purple'])
    @include('partials.ui.soft-kpi', ['iconName' => 'calendar', 'label' => 'Alertas abiertas', 'value' => $summary['open']])
</div>

<form method="GET" action="{{ route('reports.level-renewals') }}" class="card">
    <div class="fi-filter-bar renewal-filter-bar">
        <select name="status">
            <option value="">Todos los estados</option>
            <option value="open" @selected(request('status') === 'open')>Abiertas</option>
            <option value="resolved" @selected(request('status') === 'resolved')>Resueltas</option>
        </select>
        <select name="email_status">
            <option value="">Estado email</option>
            <option value="sent" @selected(request('email_status') === 'sent')>Enviado</option>
            <option value="pending" @selected(request('email_status') === 'pending')>Pendiente</option>
        </select>
        <input class="search" type="date" name="from" value="{{ request('from') }}" aria-label="Desde">
        <input class="search" type="date" name="to" value="{{ request('to') }}" aria-label="Hasta">
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

<div class="card table-card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Alumno</th>
                <th>Mensaje</th>
                <th>Fecha alerta</th>
                <th>Email</th>
                <th>Enviado</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($alerts as $alert)
                <tr>
                    <td>
                        <div class="table-title">{{ $alert->student?->full_name ?? 'Sin alumno' }}</div>
                        <div class="table-sub">{{ $alert->student?->email ?: 'Sin email' }}</div>
                    </td>
                    <td>{{ $alert->message }}</td>
                    <td>{{ $alert->created_at?->format('d/m/Y H:i') ?? 'N/D' }}</td>
                    <td>
                        @if($alert->emailed_at)
                            @include('partials.ui.status-badge', ['tone' => 'ok', 'text' => 'Enviado'])
                        @else
                            @include('partials.ui.status-badge', ['tone' => 'warn', 'text' => 'Pendiente'])
                        @endif
                    </td>
                    <td>{{ $alert->emailed_at?->format('d/m/Y H:i') ?? 'N/D' }}</td>
                    <td>@include('partials.ui.status-badge', ['tone' => $alert->status === 'open' ? 'info' : 'ok', 'text' => ucfirst($alert->status)])</td>
                    <td class="table-actions">
                        @if($alert->student)
                            <a href="{{ route('students.show', $alert->student) }}">Ver alumno</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state-inline">No hay recordatorios de renovación registrados.</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($alerts->hasPages())
        {{ $alerts->links() }}
    @endif
</div>
@endsection
