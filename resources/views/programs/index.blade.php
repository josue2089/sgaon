@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Programas</h1>
        <p class="page-subtitle">Administra las rutas académicas, sus niveles y clases base.</p>
    </div>
    <a class="btn" href="{{ route('programs.create') }}">Nuevo programa</a>
</div>

<form method="GET" action="{{ route('programs.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre o código">
        </div>
        <select name="status">
            <option value="">Todos los estados</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
        </select>
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

@if($programs->count() === 0)
    <div class="card empty-state">No hay programas para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Programa</th>
                    <th>Código</th>
                    <th>Niveles</th>
                    <th>Cursos</th>
                    <th>Estatus</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($programs as $program)
                    <tr>
                        <td>
                            <div class="table-title">{{ $program->name }}</div>
                            <div class="table-sub">{{ $program->description ?: 'Sin descripción' }}</div>
                        </td>
                        <td>{{ $program->code }}</td>
                        <td>{{ $program->levels_count }}</td>
                        <td>{{ $program->courses_count }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $program->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($program->status)])</td>
                        <td class="table-actions">
                            <a href="{{ route('programs.show', $program) }}">Ver detalle</a>
                            <a href="{{ route('programs.edit', $program) }}">Editar</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if($programs->hasPages())
        <div class="card">{{ $programs->links() }}</div>
    @endif
@endif
@endsection
