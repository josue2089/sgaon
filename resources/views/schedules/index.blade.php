@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Horarios 🕒</h1>
        <p class="page-subtitle">Define combinaciones de días y franjas horarias para usarlas en grupos</p>
    </div>
    <a class="btn" href="{{ route('schedules.create') }}">Nuevo horario</a>
</div>

<form method="GET" action="{{ route('schedules.index') }}" class="card">
    <div class="fi-filter-bar">
        <select name="day" style="max-width:220px;">
            <option value="">Todos los días</option>
            @foreach($dayOptions as $dayCode => $dayLabel)
                <option value="{{ $dayCode }}" @selected($filters['day'] === $dayCode)>{{ $dayLabel }}</option>
            @endforeach
        </select>
        <select name="status" style="max-width:220px;">
            <option value="">Todos los estados</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
        </select>
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

@if($schedules->count() === 0)
    <div class="card empty-state">No hay horarios registrados para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Días</th>
                    <th>Horario</th>
                    <th>Campus</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($schedules as $schedule)
                    <tr>
                        <td class="table-title">{{ $schedule->days_label }}</td>
                        <td>{{ $schedule->time_range_label }}</td>
                        <td>{{ $schedule->campus->name ?? 'Sin sede' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $schedule->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($schedule->status)])</td>
                        <td class="table-actions">
                            <a href="{{ route('schedules.edit', $schedule) }}">Editar</a>
                            <form method="POST" action="{{ route('schedules.destroy', $schedule) }}" onsubmit="return confirm('¿Eliminar este horario?');">
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
    @if($schedules->hasPages())
        <div class="card">{{ $schedules->links() }}</div>
    @endif
@endif
@endsection
