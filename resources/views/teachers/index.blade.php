@extends('layouts.app')
@section('content')
<div class="card"><a class="btn" href="{{ route('teachers.create') }}">Nuevo profesor</a></div>
<div class="card"><table><thead><tr><th>Nombre</th><th>Email</th><th>Campus</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($teachers as $teacher)
<tr><td>{{ $teacher->full_name }}</td><td>{{ $teacher->email }}</td><td>{{ $teacher->campus->name ?? '' }}</td><td>{{ $teacher->status }}</td><td><a href="{{ route('teachers.edit',$teacher) }}">Editar</a></td></tr>
@endforeach
</tbody></table>{{ $teachers->links() }}</div>
@endsection
