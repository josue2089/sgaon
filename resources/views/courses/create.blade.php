@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Nuevo curso</h2><p class="muted">Define nivel, código y estado del curso.</p></div></div>
<div class="card"><form method="POST" action="{{ route('courses.store') }}">@csrf @include('courses.form')<div class="form-actions"><button class="btn">Guardar</button><a class="btn secondary" href="{{ route('courses.index') }}">Volver</a></div></form></div>
@endsection
