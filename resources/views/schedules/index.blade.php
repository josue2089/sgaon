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
    <div class="entity-grid">
        @foreach($schedules as $schedule)
            <div class="entity-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                    <div>
                        <div class="entity-title">{{ $schedule->days_label }}</div>
                        <div class="entity-sub">{{ $schedule->time_range_label }}</div>
                        <div class="entity-sub">{{ $schedule->campus->name ?? 'Sin sede' }}</div>
                    </div>
                    <span class="badge-pill {{ $schedule->status === 'active' ? 'badge-ok' : 'badge-warn' }}">{{ $schedule->status }}</span>
                </div>
                <div class="form-actions">
                    <a class="btn secondary" href="{{ route('schedules.edit', $schedule) }}">Editar</a>
                    <form method="POST" action="{{ route('schedules.destroy', $schedule) }}" onsubmit="return confirm('¿Eliminar este horario?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn secondary" type="submit">Eliminar</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    @if($schedules->hasPages())
        <div class="card">{{ $schedules->links() }}</div>
    @endif
@endif
@endsection
