@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Nueva inscripción 📝</h1><p class="page-subtitle">Asocia un alumno a un grupo activo</p></div></div>
<div class="card"><form method="POST" action="{{ route('enrollments.store') }}">@csrf @include('enrollments.form')<div class="form-actions"><button class="btn">Guardar</button><a class="btn secondary" href="{{ route('enrollments.index') }}">Volver</a></div></form></div>
@endsection
