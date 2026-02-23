@extends('layouts.app')
@section('content')
<div class="card"><h2>Editar alumno</h2><form method="POST" action="{{ route('students.update',$student) }}">@csrf @method('PUT') @include('students.form')<button class="btn" type="submit">Actualizar</button></form></div>
@endsection
