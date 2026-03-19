@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Nuevo curso 📚</h1><p class="page-subtitle">Configura profesor, horario, período y generación automática de sesiones</p></div></div>
<div class="card"><form method="POST" action="{{ route('courses.store') }}">@csrf @include('courses.form')<div class="form-actions"><button class="btn">Crear curso</button><a class="btn secondary" href="{{ route('courses.index') }}">Volver</a></div></form></div>
@endsection
