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
    <div class="entity-grid">
        @foreach($periods as $period)
            <div class="entity-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                    <div>
                        <div class="entity-title">{{ $period->code }}</div>
                        <div class="entity-sub">{{ $period->description ?: 'Sin descripción' }}</div>
                        <div class="entity-sub">{{ $period->campus->name ?? 'Sin sede' }}</div>
                    </div>
                    <span class="badge-pill {{ $period->status === 'active' ? 'badge-ok' : 'badge-warn' }}">{{ $period->status }}</span>
                </div>
                <div class="form-actions">
                    <a class="btn secondary" href="{{ route('periods.edit', $period) }}">Editar</a>
                    <form method="POST" action="{{ route('periods.destroy', $period) }}" onsubmit="return confirm('¿Eliminar este período?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn secondary" type="submit">Eliminar</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    @if($periods->hasPages())
        <div class="card">{{ $periods->links() }}</div>
    @endif
@endif
@endsection
