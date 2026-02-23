@extends('layouts.app')
@section('content')
<div class="card"><h2>Nueva inscripción</h2><form method="POST" action="{{ route('enrollments.store') }}">@csrf @include('enrollments.form')<button class="btn">Guardar</button></form></div>
@endsection
