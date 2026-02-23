@extends('layouts.app')
@section('content')
<div class="card"><h2>Editar inscripción</h2><form method="POST" action="{{ route('enrollments.update',$enrollment) }}">@csrf @method('PUT') @include('enrollments.form')<button class="btn">Actualizar</button></form></div>
@endsection
