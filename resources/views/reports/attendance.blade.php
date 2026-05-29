@extends('layouts.app')
@section('content')
<div class="card">
    <div class="module-head">
        <div>
            <h1 class="page-title">Reporte de asistencia 📋</h1>
            <p class="page-subtitle">Consulta y exporta asistencia por fecha y grupo</p>
        </div>
    </div>
    <form method="GET" action="{{ route('reports.attendance') }}">
        <div class="grid-2">
            <div><label>Desde</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div><label>Hasta</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div>
                <label>Grupo</label>
                <select name="group_id">
                    <option value="">Todos</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" @selected((int) request('group_id') === (int) $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn secondary" href="{{ route('reports.attendance', array_merge(request()->query(), ['export' => 'csv'])) }}">Exportar CSV</a>
        </div>
    </form>
    <div class="form-actions">
        <form method="POST" action="{{ route('reports.exports.queue', array_merge(request()->query(), ['type' => 'attendance'])) }}">
            @csrf
            <input type="hidden" name="type" value="attendance">
            <button class="btn secondary" type="submit">Exportar en segundo plano</button>
        </form>
    </div>
    <div class="form-actions">
        <form method="POST" action="{{ route('reports.presets.store', request()->query()) }}">
            @csrf
            <input type="hidden" name="route_name" value="reports.attendance">
            <input type="text" name="name" placeholder="Nombre del preset" style="max-width:220px;">
            <button class="btn secondary" type="submit">Guardar preset</button>
        </form>
    </div>
    @if(($presets ?? collect())->count() > 0)
        <div class="stack-sm">
            @foreach($presets as $preset)
                <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
                    <a class="btn secondary" href="{{ route('reports.attendance', $preset->filters ?? []) }}">{{ $preset->name }}</a>
                    <form method="POST" action="{{ route('reports.presets.destroy', $preset) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn secondary" type="submit">Eliminar</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
    @if(($exports ?? collect())->count() > 0)
        <div class="stack-sm" style="margin-top:.8rem;">
            @foreach($exports as $export)
                <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
                    @include('partials.ui.status-badge', ['tone' => $export->status === 'done' ? 'ok' : ($export->status === 'failed' ? 'danger' : 'warn'), 'text' => strtoupper($export->status)])
                    <span class="entity-sub">{{ $export->created_at?->format('Y-m-d H:i') }}</span>
                    @if($export->status === 'done')
                        <a class="btn secondary" href="{{ route('reports.exports.download', $export) }}">Descargar</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
<div class="card">
    <table>
        <thead><tr><th>Fecha</th><th>Grupo</th><th>Alumno</th><th>Status</th><th>Comentario</th></tr></thead>
        <tbody>
        @forelse($records as $record)
            <tr>
                <td>{{ $record->classSession->session_date?->format('Y-m-d') }}</td>
                <td>{{ $record->classSession->group->name ?? '' }}</td>
                <td>{{ $record->enrollment->student->full_name ?? '' }}</td>
                <td><span class="status-pill {{ $record->status === 'present' ? 'success' : ($record->status === 'absent' ? 'danger' : 'warn') }}">{{ $record->status }}</span></td>
                <td>{{ $record->notes ?: '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <div class="empty-state-inline">Sin registros para el rango seleccionado.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    @if($records->hasPages())
        {{ $records->links() }}
    @endif
</div>
@endsection
