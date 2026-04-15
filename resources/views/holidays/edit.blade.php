@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar feriado</h1>
        <p class="page-subtitle">Actualiza nombre, alcance y tipo del feriado.</p>
    </div>
</div>

<form method="POST" action="{{ route('holidays.update', $holiday) }}" class="card">
    @csrf
    @method('PUT')
    @include('holidays.form')
    <button class="btn" type="submit">Guardar cambios</button>
</form>
@endsection
