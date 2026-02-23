@extends('layouts.app')
@section('content')
<div class="card"><a class="btn" href="{{ route('sessions.create') }}">Nueva sesión</a></div>
<div class="card"><table><thead><tr><th>Fecha</th><th>Grupo</th><th>Tema</th><th></th></tr></thead><tbody>
@foreach($sessions as $session)
<tr><td>{{ $session->session_date?->format('Y-m-d') }}</td><td>{{ $session->group->name ?? '' }}</td><td>{{ $session->topic }}</td><td><a href="{{ route('sessions.edit',$session) }}">Editar</a></td></tr>
@endforeach
</tbody></table>{{ $sessions->links() }}</div>
@endsection
