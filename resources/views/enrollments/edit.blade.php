@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Editar inscripción ✏️</h1><p class="page-subtitle">Ajusta estado y progreso del alumno inscrito</p></div></div>
<div class="card"><form method="POST" action="{{ route('enrollments.update',$enrollment) }}">@csrf @method('PUT') @include('enrollments.form')<div class="form-actions"><button class="btn">Actualizar</button><a class="btn secondary" href="{{ route('enrollments.index') }}">Volver</a></div></form></div>
@endsection
