@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Feriados</h1>
        <p class="page-subtitle">Catálogo de días no laborables para evitar programar clases en esas fechas.</p>
    </div>
    <a class="btn" href="{{ route('holidays.create') }}">Nuevo feriado</a>
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
                        <td>{{ $holiday->is_recurring ? 'Recurrente' : 'Fecha puntual' }}</td>
                        <td>{{ $holiday->occurrence_label }}</td>
                        <td>{{ $holiday->campus->name ?? 'Global' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $holiday->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($holiday->status)])</td>
                        <td class="table-actions">
                            <a href="{{ route('holidays.edit', $holiday) }}">Editar</a>
                            <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" onsubmit="return confirm('¿Eliminar este feriado?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn-link-danger" type="submit">Eliminar</button>
                            </form>
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
