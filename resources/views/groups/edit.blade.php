@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Editar grupo</h2><p class="muted">Actualiza calendario y asignaciones del grupo.</p></div></div>
<div class="card"><form method="POST" action="{{ route('groups.update',$group) }}">@csrf @method('PUT') @include('groups.form')<div class="form-actions"><button class="btn">Actualizar</button><a class="btn secondary" href="{{ route('groups.index') }}">Volver</a></div></form></div>
@endsection
