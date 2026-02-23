@extends('layouts.app')
@section('content')
<div class="card"><h2>Editar grupo</h2><form method="POST" action="{{ route('groups.update',$group) }}">@csrf @method('PUT') @include('groups.form')<button class="btn">Actualizar</button></form></div>
@endsection
