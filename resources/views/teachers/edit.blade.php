@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Editar profesor</h2><p class="muted">Actualiza información del docente.</p></div></div>
<div class="card"><form method="POST" action="{{ route('teachers.update',$teacher) }}">@csrf @method('PUT') @include('teachers.form')<div class="form-actions"><button class="btn">Actualizar</button><a class="btn secondary" href="{{ route('teachers.index') }}">Volver</a></div></form></div>
@endsection
