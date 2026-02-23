@extends('layouts.app')
@section('content')
<div class="card"><a class="btn" href="{{ route('enrollments.create') }}">Nueva inscripción</a></div>
<div class="card"><table><thead><tr><th>Alumno</th><th>Grupo</th><th>Status</th><th>Progreso</th><th></th></tr></thead><tbody>
@foreach($enrollments as $enrollment)
<tr><td>{{ $enrollment->student->full_name ?? '' }}</td><td>{{ $enrollment->group->name ?? '' }}</td><td>{{ $enrollment->status }}</td><td>{{ $enrollment->progress }}%</td><td><a href="{{ route('enrollments.edit',$enrollment) }}">Editar</a></td></tr>
@endforeach
</tbody></table>{{ $enrollments->links() }}</div>
@endsection
