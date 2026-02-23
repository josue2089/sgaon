@extends('layouts.app')
@section('content')
<div class="card"><h2>Nuevo grupo</h2><form method="POST" action="{{ route('groups.store') }}">@csrf @include('groups.form')<button class="btn">Guardar</button></form></div>
@endsection
