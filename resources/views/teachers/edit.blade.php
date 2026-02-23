@extends('layouts.app')
@section('content')
<div class="card"><h2>Editar profesor</h2><form method="POST" action="{{ route('teachers.update',$teacher) }}">@csrf @method('PUT') @include('teachers.form')<button class="btn">Actualizar</button></form></div>
@endsection
