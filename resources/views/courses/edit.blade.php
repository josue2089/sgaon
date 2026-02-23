@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Editar curso ✏️</h1><p class="page-subtitle">Actualiza la configuración del curso</p></div></div>
<div class="card"><form method="POST" action="{{ route('courses.update',$course) }}">@csrf @method('PUT') @include('courses.form')<div class="form-actions"><button class="btn">Actualizar</button><a class="btn secondary" href="{{ route('courses.index') }}">Volver</a></div></form></div>
@endsection
