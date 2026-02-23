@extends('layouts.app')
@section('content')
<div class="card"><h2>Portal del Representante</h2><p><strong>{{ $representative->first_name }} {{ $representative->last_name }}</strong> | {{ $representative->email }}</p></div>
@forelse($students as $student)
<div class="card">
<h3>{{ $student->full_name }}</h3>
<p class="muted">Estado: {{ $student->status }}</p>
<div class="grid-2">
<div>
<h4>Inscripciones</h4>
<table><thead><tr><th>Curso</th><th>Grupo</th><th>Estado</th></tr></thead><tbody>@foreach($student->enrollments as $enrollment)<tr><td>{{ $enrollment->group->course->name ?? '' }}</td><td>{{ $enrollment->group->name ?? '' }}</td><td>{{ $enrollment->status }}</td></tr>@endforeach</tbody></table>
</div>
<div>
<h4>Resumen financiero</h4>
<table><thead><tr><th>Cargos</th><th>Pagos</th><th>Saldo</th></tr></thead><tbody><tr><td>${{ number_format($student->charges->sum('amount'),2) }}</td><td>${{ number_format($student->payments->sum('amount'),2) }}</td><td>${{ number_format($student->charges->sum('amount') - $student->payments->sum('amount'),2) }}</td></tr></tbody></table>
</div>
</div>
</div>
@empty
<div class="card">No hay alumnos asociados a este representante.</div>
@endforelse
@endsection
