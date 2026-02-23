@extends('layouts.app')
@section('content')
<div class="card"><h2>Nuevo curso</h2><form method="POST" action="{{ route('courses.store') }}">@csrf @include('courses.form')<button class="btn">Guardar</button></form></div>
@endsection
