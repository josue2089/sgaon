@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Editar alumno</h2><p class="muted">Actualiza datos personales y académicos del alumno.</p></div></div>
<div class="card"><form method="POST" action="{{ route('students.update',$student) }}">@csrf @method('PUT') @include('students.form')<div class="form-actions"><button class="btn" type="submit">Actualizar</button><a class="btn secondary" href="{{ route('students.index') }}">Volver</a></div></form></div>
@endsection
