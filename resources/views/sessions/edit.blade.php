@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Editar sesión</h2><p class="muted">Ajusta fecha, hora y tema de la sesión.</p></div></div>
<div class="card"><form method="POST" action="{{ route('sessions.update',$session) }}">@csrf @method('PUT') @include('sessions.form')<div class="form-actions"><button class="btn">Actualizar</button><a class="btn secondary" href="{{ route('sessions.index') }}">Volver</a></div></form></div>
@endsection
