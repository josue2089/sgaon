@extends('layouts.app')
@section('content')
<div class="card"><h2>Registro de asistencia</h2>
<form method="GET" action="{{ route('attendance.index') }}">
<select name="class_session_id"><option value="">Seleccione sesión</option>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected(request('class_session_id')==$session->id)>{{ $session->session_date?->format('Y-m-d') }} - {{ $session->group->name ?? '' }}</option>@endforeach</select>
<button class="btn" type="submit">Cargar</button>
</form></div>
@if($selectedSession)
<div class="card">
<form method="POST" action="{{ route('attendance.store') }}">@csrf
<input type="hidden" name="class_session_id" value="{{ $selectedSession->id }}">
<table><thead><tr><th>Alumno</th><th>Status</th><th>Nota</th></tr></thead><tbody>
@foreach($enrollments as $i => $enrollment)
<tr><td>{{ $enrollment->student->full_name }}</td><td><input type="hidden" name="records[{{ $i }}][enrollment_id]" value="{{ $enrollment->id }}"><select name="records[{{ $i }}][status]">@foreach($statuses as $status)<option value="{{ $status }}" @selected(($records[$enrollment->id]->status ?? 'present')==$status)>{{ $status }}</option>@endforeach</select></td><td><input name="records[{{ $i }}][notes]" value="{{ $records[$enrollment->id]->notes ?? '' }}"></td></tr>
@endforeach
</tbody></table>
<button class="btn" type="submit">Guardar asistencia</button>
</form>
</div>
@endif
@endsection
