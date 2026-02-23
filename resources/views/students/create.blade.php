@extends('layouts.app')
@section('content')
<div class="card"><h2>Nuevo alumno</h2><form method="POST" action="{{ route('students.store') }}">@csrf @include('students.form')<button class="btn" type="submit">Guardar</button></form></div>
@endsection
