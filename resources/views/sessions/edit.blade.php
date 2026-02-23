@extends('layouts.app')
@section('content')
<div class="card"><h2>Editar sesión</h2><form method="POST" action="{{ route('sessions.update',$session) }}">@csrf @method('PUT') @include('sessions.form')<button class="btn">Actualizar</button></form></div>
@endsection
