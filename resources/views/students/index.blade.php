@extends('layouts.app')
@section('content')
<div class="card"><a class="btn" href="{{ route('students.create') }}">Nuevo alumno</a></div>
<div class="card"><table><thead><tr><th>Nombre</th><th>Documento</th><th>Email</th><th>Campus</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($students as $student)
<tr><td>{{ $student->full_name }}</td><td>{{ $student->document_id }}</td><td>{{ $student->email }}</td><td>{{ $student->campus->name ?? '' }}</td><td>{{ $student->status }}</td><td><a href="{{ route('students.edit',$student) }}">Editar</a></td></tr>
@endforeach
</tbody></table>{{ $students->links() }}</div>
@endsection
