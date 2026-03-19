@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Escalas</h1>
        <p class="page-subtitle">Progresión general de niveles y recordatorios automáticos</p>
    </div>
    <a class="btn" href="{{ route('course-levels.create') }}">Nueva escala</a>
</div>

<form method="GET" action="{{ route('course-levels.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre, código, etapa o CEFR">
        </div>
        <select name="status" style="max-width:220px;">
            <option value="">Todos los estados</option>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

@if($levels->count() === 0)
    <div class="card empty-state">No hay escalas registradas para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Escala</th>
                    <th>Nombre</th>
                    <th>Etapa</th>
                    <th>CEFR</th>
                    <th>Recordatorio</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($levels as $level)
                    <tr>
                        <td class="table-title">{{ $level->scale_position }}/{{ $level->scale_total }}</td>
                        <td>
                            <div class="table-title">{{ $level->name }}</div>
                            <div class="table-sub">{{ $level->code }}</div>
                        </td>
                        <td>{{ $level->stage }}</td>
                        <td>{{ $level->cefr_reference ?: 'N/D' }}</td>
                        <td>{{ $level->reminder_days_before }} día(s)</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $level->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($level->status)])</td>
                        <td class="table-actions">
                            <a href="{{ route('course-levels.edit', $level) }}">Editar</a>
                            <form method="POST" action="{{ route('course-levels.destroy', $level) }}" onsubmit="return confirm('¿Eliminar esta escala?');">
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
    @if($levels->hasPages())
        <div class="card">{{ $levels->links() }}</div>
    @endif
@endif
@endsection
