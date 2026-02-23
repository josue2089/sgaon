@extends('layouts.app')
@section('content')
<div class="card"><h2>Editar curso</h2><form method="POST" action="{{ route('courses.update',$course) }}">@csrf @method('PUT') @include('courses.form')<button class="btn">Actualizar</button></form></div>
@endsection
