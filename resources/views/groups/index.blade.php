@extends('layouts.app')
@section('content')
<div class="card module-head">
    <div>
        <h2>Grupos</h2>
        <p class="muted">Programación de grupos, docentes y horarios.</p>
    </div>
    <a class="btn" href="{{ route('groups.create') }}">Nuevo grupo</a>
</div>
<div class="card"><table><thead><tr><th>Grupo</th><th>Curso</th><th>Profesor</th><th>Horario</th><th class="nowrap"></th></tr></thead><tbody>
@foreach($groups as $group)
<tr>
    <td>{{ $group->name }}</td>
    <td>{{ $group->course->name ?? '' }}</td>
    <td>{{ $group->teacher->full_name ?? 'Sin asignar' }}</td>
    <td>{{ $group->schedule }}</td>
    <td class="nowrap"><a href="{{ route('groups.edit',$group) }}">Editar</a></td>
</tr>
@endforeach
</tbody></table>{{ $groups->links() }}</div>
@endsection
