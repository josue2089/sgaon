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
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn secondary" href="{{ route('reports.attendance', array_merge(request()->query(), ['export' => 'csv'])) }}">Exportar CSV</a>
        </div>
    </form>
</div>
<div class="card"><table><thead><tr><th>Fecha</th><th>Grupo</th><th>Alumno</th><th>Status</th></tr></thead><tbody>
@foreach($records as $record)
<tr><td>{{ $record->classSession->session_date?->format('Y-m-d') }}</td><td>{{ $record->classSession->group->name ?? '' }}</td><td>{{ $record->enrollment->student->full_name ?? '' }}</td><td><span class="status-pill {{ $record->status === 'present' ? 'success' : ($record->status === 'absent' ? 'danger' : 'warn') }}">{{ $record->status }}</span></td></tr>
@endforeach
</tbody></table>
@if($records->hasPages())
    {{ $records->links() }}
@endif
</div>
@endsection
