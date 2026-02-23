@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Nuevo alumno</h2><p class="muted">Registra un nuevo alumno en el sistema.</p></div></div>
<div class="card"><form method="POST" action="{{ route('students.store') }}">@csrf @include('students.form')<div class="form-actions"><button class="btn" type="submit">Guardar</button><a class="btn secondary" href="{{ route('students.index') }}">Volver</a></div></form></div>
@endsection
