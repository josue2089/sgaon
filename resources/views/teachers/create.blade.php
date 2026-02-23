@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Nuevo profesor</h2><p class="muted">Registra un docente y su sede operativa.</p></div></div>
<div class="card"><form method="POST" action="{{ route('teachers.store') }}">@csrf @include('teachers.form')<div class="form-actions"><button class="btn">Guardar</button><a class="btn secondary" href="{{ route('teachers.index') }}">Volver</a></div></form></div>
@endsection
