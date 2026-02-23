@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Nueva inscripción</h2><p class="muted">Asocia un alumno a un grupo activo.</p></div></div>
<div class="card"><form method="POST" action="{{ route('enrollments.store') }}">@csrf @include('enrollments.form')<div class="form-actions"><button class="btn">Guardar</button><a class="btn secondary" href="{{ route('enrollments.index') }}">Volver</a></div></form></div>
@endsection
