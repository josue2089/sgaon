@extends('layouts.app')
@section('content')
<div class="card"><h2>Nueva sesión</h2><form method="POST" action="{{ route('sessions.store') }}">@csrf @include('sessions.form')<button class="btn">Guardar</button></form></div>
@endsection
