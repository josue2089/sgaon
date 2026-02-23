@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Nuevo alumno 👨‍🎓</h1><p class="page-subtitle">Registra un nuevo alumno en el sistema</p></div></div>
<div class="card"><form method="POST" action="{{ route('students.store') }}">@csrf @include('students.form')<div class="form-actions"><button class="btn" type="submit">Guardar</button><a class="btn secondary" href="{{ route('students.index') }}">Volver</a></div></form></div>
@endsection
