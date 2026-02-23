@extends('layouts.app')
@section('content')
<div class="card"><h2>Reporte de asistencia</h2><form method="GET" class="grid-2"><div><label>Desde</label><input type="date" name="from" value="{{ request('from') }}"></div><div><label>Hasta</label><input type="date" name="to" value="{{ request('to') }}"></div><button class="btn" type="submit">Filtrar</button><a class="btn secondary" href="{{ route('reports.attendance', array_merge(request()->query(), ['export' => 'csv'])) }}">Exportar CSV</a></form></div>
<div class="card"><table><thead><tr><th>Fecha</th><th>Grupo</th><th>Alumno</th><th>Status</th></tr></thead><tbody>
@foreach($records as $record)
<tr><td>{{ $record->classSession->session_date?->format('Y-m-d') }}</td><td>{{ $record->classSession->group->name ?? '' }}</td><td>{{ $record->enrollment->student->full_name ?? '' }}</td><td>{{ $record->status }}</td></tr>
@endforeach
</tbody></table>{{ $records->links() }}</div>
@endsection
