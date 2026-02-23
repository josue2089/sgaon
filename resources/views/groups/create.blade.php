@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Nuevo grupo 🧩</h1><p class="page-subtitle">Crea un grupo académico y asigna curso/profesor</p></div></div>
<div class="card"><form method="POST" action="{{ route('groups.store') }}">@csrf @include('groups.form')<div class="form-actions"><button class="btn">Guardar</button><a class="btn secondary" href="{{ route('groups.index') }}">Volver</a></div></form></div>
@endsection
