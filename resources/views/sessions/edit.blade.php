@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Editar sesión ✏️</h1><p class="page-subtitle">Ajusta fecha, hora y tema de la sesión</p></div></div>
<div class="card"><form method="POST" action="{{ route('sessions.update',$session) }}">@csrf @method('PUT') @include('sessions.form')<div class="form-actions"><button class="btn">Actualizar</button><a class="btn secondary" href="{{ route('sessions.index') }}">Volver</a></div></form></div>
@endsection
