@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Editar profesor ✏️</h1><p class="page-subtitle">Actualiza información del docente</p></div></div>
<div class="card"><form method="POST" action="{{ route('teachers.update',$teacher) }}" enctype="multipart/form-data">@csrf @method('PUT') @include('teachers.form')<div class="form-actions"><button class="btn">Actualizar</button><a class="btn secondary" href="{{ route('teachers.index') }}">Volver</a></div></form></div>
@endsection
