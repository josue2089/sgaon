@extends('layouts.app')
@section('content')
{{-- Auditoría legible y filtrable. #451 --}}
<div class="module-head">
    <div>
        <h1 class="page-title">Auditoría</h1>
        <p class="page-subtitle">Quién hizo qué y cuándo, con los datos que cambiaron.</p>
    </div>
</div>
@include('partials.layout.reports-tabs')

<form method="GET" action="{{ route('reports.audit') }}" class="card">
    <div class="fi-filter-bar">
        <select name="user_id" aria-label="Usuario">
            <option value="">Todos los usuarios</option>
            @foreach($users as $auditUser)
                <option value="{{ $auditUser->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $auditUser->id)>{{ $auditUser->name ?: $auditUser->email }}</option>
            @endforeach
        </select>
        <select name="action" aria-label="Acción">
            <option value="">Todas las acciones</option>
            @foreach($actionGroups as $group => $actions)
                <optgroup label="{{ \Illuminate\Support\Str::ucfirst(str_replace('_', ' ', $group)) }}">
                    <option value="{{ $group }}.*" @selected(($filters['action'] ?? '') === $group.'.*')>Todo: {{ str_replace('_', ' ', $group) }}</option>
                    @foreach($actions as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['action'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <select name="entity" aria-label="Entidad">
            <option value="">Todas las entidades</option>
            @foreach($entities as $class => $label)
                <option value="{{ $class }}" @selected(($filters['entity'] ?? '') === $class)>{{ $label }}</option>
            @endforeach
        </select>
        @if($campuses->count() > 1)
            <select name="campus_id" aria-label="Sede">
                <option value="">Todas las sedes</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) ($filters['campus_id'] ?? '') === (string) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        @endif
        <label class="fi-check">Desde <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="fi-check">Hasta <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button class="btn secondary" type="submit">Filtrar</button>
        @if(array_filter($filters))
            <a class="btn secondary" href="{{ route('reports.audit') }}">Quitar filtros</a>
        @endif
    </div>
</form>

<div class="card table-card">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Sobre</th><th scope="col">Detalle</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $log->user?->name ?: ($log->user?->email ?: 'Sistema') }}</td>
                    <td><strong>{{ \App\Support\AuditLabel::action($log->action) }}</strong></td>
                    <td>{{ \App\Support\AuditLabel::entity($log->auditable_type, $log->auditable_id) }}</td>
                    <td>@include('partials.ui.audit-changes', ['log' => $log])</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-inline">Sin registros para estos filtros.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
        {{ $logs->links() }}
    @endif
</div>
@endsection
