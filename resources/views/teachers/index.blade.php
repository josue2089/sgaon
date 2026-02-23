@extends('layouts.app')
@section('content')
<div class="card module-head">
    <div>
        <h2>Profesores</h2>
        <p class="muted">Gestión de docentes y estado operativo.</p>
    </div>
    <a class="btn" href="{{ route('teachers.create') }}">Nuevo profesor</a>
</div>
<div class="card"><table><thead><tr><th>Nombre</th><th>Email</th><th>Campus</th><th>Status</th><th class="nowrap"></th></tr></thead><tbody>
@foreach($teachers as $teacher)
<tr>
    <td>{{ $teacher->full_name }}</td>
    <td>{{ $teacher->email }}</td>
    <td>{{ $teacher->campus->name ?? '' }}</td>
    <td><span class="status-pill {{ $teacher->status === 'active' ? 'success' : 'warn' }}">{{ $teacher->status }}</span></td>
    <td class="nowrap"><a href="{{ route('teachers.edit',$teacher) }}">Editar</a></td>
</tr>
@endforeach
</tbody></table>{{ $teachers->links() }}</div>
@endsection
