@extends('layouts.app')
@section('content')
<div class="card module-head">
    <div>
        <h2>Sesiones</h2>
        <p class="muted">Agenda de clases por grupo.</p>
    </div>
    <a class="btn" href="{{ route('sessions.create') }}">Nueva sesión</a>
</div>
<div class="card"><table><thead><tr><th>Fecha</th><th>Grupo</th><th>Tema</th><th class="nowrap"></th></tr></thead><tbody>
@foreach($sessions as $session)
<tr>
    <td>{{ $session->session_date?->format('Y-m-d') }}</td>
    <td>{{ $session->group->name ?? '' }}</td>
    <td>{{ $session->topic }}</td>
    <td class="nowrap"><a href="{{ route('sessions.edit',$session) }}">Editar</a></td>
</tr>
@endforeach
</tbody></table>{{ $sessions->links() }}</div>
@endsection
