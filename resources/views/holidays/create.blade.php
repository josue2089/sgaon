@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nuevo feriado</h1>
        <p class="page-subtitle">Registra una fecha puntual o un feriado recurrente anual.</p>
    </div>
</div>

<form method="POST" action="{{ route('holidays.store') }}" class="card">
    @csrf
    @include('holidays.form')
    <button class="btn" type="submit">Guardar</button>
</form>
@endsection
