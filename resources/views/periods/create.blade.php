@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nuevo período 🗂️</h1>
        <p class="page-subtitle">Crea un identificador informativo para organizar la operación académica</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('periods.store') }}">
        @csrf
        @include('periods.form')
        <div class="form-actions">
            <button class="btn" type="submit">Guardar período</button>
            <a class="btn secondary" href="{{ route('periods.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
