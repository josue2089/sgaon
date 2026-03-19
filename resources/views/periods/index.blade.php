@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Períodos 🗂️</h1>
        <p class="page-subtitle">Catálogo informativo para organizar ciclos como 2026-Q1 o 2026-Q2</p>
    </div>
    <a class="btn" href="{{ route('periods.create') }}">Nuevo período</a>
</div>

<form method="GET" action="{{ route('periods.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por código o descripción">
        </div>
        <select name="status" style="max-width:220px;">
            <option value="">Todos los estados</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
        </select>
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

@if($periods->count() === 0)
    <div class="card empty-state">No hay períodos registrados para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Campus</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($periods as $period)
                    <tr>
                        <td class="table-title">{{ $period->code }}</td>
                        <td>{{ $period->description ?: 'Sin descripción' }}</td>
                        <td>{{ $period->campus->name ?? 'Sin sede' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $period->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($period->status)])</td>
                        <td class="table-actions">
                            <a href="{{ route('periods.edit', $period) }}">Editar</a>
                            <form method="POST" action="{{ route('periods.destroy', $period) }}" onsubmit="return confirm('¿Eliminar este período?');">
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
    @if($periods->hasPages())
        <div class="card">{{ $periods->links() }}</div>
    @endif
@endif
@endsection
