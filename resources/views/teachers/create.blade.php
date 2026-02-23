@extends('layouts.app')
@section('content')
<div class="card"><h2>Nuevo profesor</h2><form method="POST" action="{{ route('teachers.store') }}">@csrf @include('teachers.form')<button class="btn">Guardar</button></form></div>
@endsection
