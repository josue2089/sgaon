@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar programa</h1>
        <p class="page-subtitle">Actualiza datos base del programa.</p>
    </div>
    <a class="btn secondary" href="{{ route('programs.show', $program) }}">Volver</a>
</div>

<form method="POST" action="{{ route('programs.update', $program) }}" class="card stack-sm">
    @csrf
    @method('PUT')
    @include('programs.form')
    <div class="form-actions">
        <button class="btn" type="submit">Actualizar programa</button>
    </div>
</form>
@endsection
