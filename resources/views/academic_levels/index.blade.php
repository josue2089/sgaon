@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Niveles</h1>
        <p class="page-subtitle">Catálogo académico base por campus</p>
    </div>
    <a class="btn" href="{{ route('academic-levels.create') }}">Nuevo nivel</a>
</div>

<form method="GET" action="{{ route('academic-levels.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre, código o descripción">
        </div>
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

@if($levels->count() === 0)
    <div class="card empty-state">No hay niveles registrados para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Código</th>
                    <th>Campus</th>
                    <th>Orden</th>
                    <th>Descripción</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($levels as $level)
                    <tr>
                        <td class="table-title">{{ $level->name }}</td>
                        <td>{{ $level->code ?: 'Sin código' }}</td>
                        <td>{{ $level->campus?->name ?? 'N/D' }}</td>
                        <td>{{ $level->sort_order }}</td>
                        <td>{{ $level->description ?: 'Sin descripción' }}</td>
                        <td class="table-actions">
                            <a href="{{ route('academic-levels.edit', $level) }}">Editar</a>
                            <form method="POST" action="{{ route('academic-levels.destroy', $level) }}" onsubmit="return confirm('¿Eliminar este nivel?');">
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
