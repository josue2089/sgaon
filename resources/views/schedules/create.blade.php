@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nuevo horario 🕒</h1>
        <p class="page-subtitle">Selecciona días de la semana y define una franja operativa</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('schedules.store') }}">
        @csrf
        @include('schedules.form')
        <div class="form-actions">
            <button class="btn" type="submit">Guardar horario</button>
            <a class="btn secondary" href="{{ route('schedules.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
