@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Editar inscripción</h2><p class="muted">Ajusta estado y progreso del alumno inscrito.</p></div></div>
<div class="card"><form method="POST" action="{{ route('enrollments.update',$enrollment) }}">@csrf @method('PUT') @include('enrollments.form')<div class="form-actions"><button class="btn">Actualizar</button><a class="btn secondary" href="{{ route('enrollments.index') }}">Volver</a></div></form></div>
@endsection
