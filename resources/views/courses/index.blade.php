@extends('layouts.app')
@section('content')
<div class="card"><a class="btn" href="{{ route('courses.create') }}">Nuevo curso</a></div>
<div class="card"><table><thead><tr><th>Curso</th><th>Nivel</th><th>Campus</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($courses as $course)
<tr><td>{{ $course->name }}</td><td>{{ $course->level->name ?? '' }}</td><td>{{ $course->campus->name ?? '' }}</td><td>{{ $course->status }}</td><td><a href="{{ route('courses.edit',$course) }}">Editar</a></td></tr>
@endforeach
</tbody></table>{{ $courses->links() }}</div>
@endsection
