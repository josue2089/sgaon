@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Feriados y días sin clase</h1>
        <p class="page-subtitle">Días no laborables y días sin clase de cada colegio. Las clases de los cursos de la sede se recalculan al guardar.</p>
    </div>
    <a class="btn" href="{{ route('holidays.create') }}">{{ auth()->user()->isMasterAdmin() ? 'Nuevo feriado' : 'Nuevo día sin clase' }}</a>
</div>

<form method="GET" action="{{ route('holidays.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre o descripción">
        </div>
        <select name="type" style="max-width:220px;">
            <option value="">Todos los tipos</option>
            <option value="dated" @selected($filters['type'] === 'dated')>Fecha puntual</option>
            <option value="recurring" @selected($filters['type'] === 'recurring')>Recurrentes</option>
            <option value="school_closure" @selected($filters['type'] === 'school_closure')>Días sin clase del colegio</option>
        </select>
        <select name="status" style="max-width:220px;">
            <option value="">Todos los estados</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
        </select>
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

@if($holidays->count() === 0)
    <div class="card empty-state">No hay feriados registrados para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Feriado</th>
                    <th>Tipo</th>
                    <th>Ocurrencia</th>
                    <th>Campus</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($holidays as $holiday)
                    <tr>
                        <td class="table-title">{{ $holiday->name }}</td>
                        <td>
                            {{ $holiday->kind_label }}
                            <div class="table-sub">{{ $holiday->is_recurring ? 'Recurrente' : ($holiday->end_date ? 'Rango de fechas' : 'Fecha puntual') }}</div>
                        </td>
                        <td>{{ $holiday->occurrence_label }}</td>
                        <td>{{ $holiday->campus->name ?? 'Global' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $holiday->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($holiday->status)])</td>
                        <td class="table-actions">
                            @if(auth()->user()->isMasterAdmin() || $holiday->campus_id)
                            <a href="{{ route('holidays.edit', $holiday) }}">Editar</a>
                            <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" onsubmit="return confirm('¿Eliminar este feriado?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn-link-danger" type="submit">Eliminar</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if($holidays->hasPages())
        <div class="card">{{ $holidays->links() }}</div>
    @endif
@endif
@endsection
