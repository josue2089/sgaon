@extends('layouts.app')
@section('content')
<div class="card module-head">
    <div>
        <h2>Cursos</h2>
        <p class="muted">Catálogo académico por niveles y sede.</p>
    </div>
    <a class="btn" href="{{ route('courses.create') }}">Nuevo curso</a>
</div>
<div class="card"><table><thead><tr><th>Curso</th><th>Nivel</th><th>Campus</th><th>Status</th><th class="nowrap"></th></tr></thead><tbody>
@foreach($courses as $course)
<tr>
    <td>{{ $course->name }}</td>
    <td>{{ $course->level->name ?? '' }}</td>
    <td>{{ $course->campus->name ?? '' }}</td>
    <td><span class="status-pill {{ $course->status === 'active' ? 'success' : 'warn' }}">{{ $course->status }}</span></td>
    <td class="nowrap"><a href="{{ route('courses.edit',$course) }}">Editar</a></td>
</tr>
@endforeach
</tbody></table>{{ $courses->links() }}</div>
@endsection
