@extends('layouts.app')
@section('content')
<div class="card module-head">
    <div>
        <h2>Inscripciones</h2>
        <p class="muted">Control de alumnos inscritos por grupo.</p>
    </div>
    <a class="btn" href="{{ route('enrollments.create') }}">Nueva inscripción</a>
</div>
<div class="card"><table><thead><tr><th>Alumno</th><th>Grupo</th><th>Status</th><th>Progreso</th><th class="nowrap"></th></tr></thead><tbody>
@foreach($enrollments as $enrollment)
<tr>
    <td>{{ $enrollment->student->full_name ?? '' }}</td>
    <td>{{ $enrollment->group->name ?? '' }}</td>
    <td><span class="status-pill {{ $enrollment->status === 'active' ? 'success' : ($enrollment->status === 'completed' ? 'info' : 'warn') }}">{{ $enrollment->status }}</span></td>
    <td>{{ $enrollment->progress }}%</td>
    <td class="nowrap"><a href="{{ route('enrollments.edit',$enrollment) }}">Editar</a></td>
</tr>
@endforeach
</tbody></table>{{ $enrollments->links() }}</div>
@endsection
