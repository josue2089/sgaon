@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nuevo programa</h1>
        <p class="page-subtitle">Crea una ruta académica principal.</p>
    </div>
    <a class="btn secondary" href="{{ route('programs.index') }}">Volver</a>
</div>

<form method="POST" action="{{ route('programs.store') }}" class="card stack-sm">
    @csrf
    @include('programs.form')
    <div class="form-actions">
        <button class="btn" type="submit">Guardar programa</button>
    </div>
</form>
@endsection
