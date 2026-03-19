@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Campus</h1>
        <p class="page-subtitle">Administra las sedes disponibles en la plataforma</p>
    </div>
    <a class="btn" href="{{ route('campuses.create') }}">Nuevo campus</a>
</div>

<form method="GET" action="{{ route('campuses.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre, código o ubicación">
        </div>
        <select name="status" style="max-width:220px;">
            <option value="">Todos los estados</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
        </select>
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

@if($campuses->count() === 0)
    <div class="card empty-state">No hay campus registrados para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Campus</th>
                    <th>Código</th>
                    <th>Ubicación</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($campuses as $campus)
                    <tr>
                        <td class="table-title">{{ $campus->name }}</td>
                        <td>{{ $campus->code }}</td>
                        <td>{{ collect([$campus->city, $campus->state, $campus->country])->filter()->implode(', ') ?: 'Sin ubicación' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $campus->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($campus->status)])</td>
                        <td class="table-actions">
                            <a href="{{ route('campuses.edit', $campus) }}">Editar</a>
                            <form method="POST" action="{{ route('campuses.destroy', $campus) }}" onsubmit="return confirm('¿Eliminar este campus?');">
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
    @if($campuses->hasPages())
        <div class="card">{{ $campuses->links() }}</div>
    @endif
@endif
@endsection
