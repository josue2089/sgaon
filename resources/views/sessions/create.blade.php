@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Nueva sesión 🗓️</h1><p class="page-subtitle">Programa fecha y horario de clase</p></div></div>
<div class="card"><form method="POST" action="{{ route('sessions.store') }}">@csrf @include('sessions.form')<div class="form-actions"><button class="btn">Guardar</button><a class="btn secondary" href="{{ route('sessions.index') }}">Volver</a></div></form></div>
@endsection
