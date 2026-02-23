@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Nuevo grupo</h2><p class="muted">Crea un grupo académico y asigna curso/profesor.</p></div></div>
<div class="card"><form method="POST" action="{{ route('groups.store') }}">@csrf @include('groups.form')<div class="form-actions"><button class="btn">Guardar</button><a class="btn secondary" href="{{ route('groups.index') }}">Volver</a></div></form></div>
@endsection
