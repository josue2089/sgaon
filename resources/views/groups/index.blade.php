@extends('layouts.app')
@section('content')
<div class="card"><a class="btn" href="{{ route('groups.create') }}">Nuevo grupo</a></div>
<div class="card"><table><thead><tr><th>Grupo</th><th>Curso</th><th>Profesor</th><th>Horario</th><th></th></tr></thead><tbody>
@foreach($groups as $group)
<tr><td>{{ $group->name }}</td><td>{{ $group->course->name ?? '' }}</td><td>{{ $group->teacher->full_name ?? '' }}</td><td>{{ $group->schedule }}</td><td><a href="{{ route('groups.edit',$group) }}">Editar</a></td></tr>
@endforeach
</tbody></table>{{ $groups->links() }}</div>
@endsection
