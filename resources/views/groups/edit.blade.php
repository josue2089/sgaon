@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Editar grupo ✏️</h1><p class="page-subtitle">Actualiza calendario y asignaciones del grupo</p></div></div>
<div class="card"><form method="POST" action="{{ route('groups.update',$group) }}">@csrf @method('PUT') @include('groups.form')<div class="form-actions"><button class="btn">Actualizar</button><a class="btn secondary" href="{{ route('groups.index') }}">Volver</a></div></form></div>
@endsection
