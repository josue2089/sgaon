@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar horario ✏️</h1>
        <p class="page-subtitle">Ajusta días, tramo horario o estado del catálogo</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('schedules.update', $schedule) }}">
        @csrf
        @method('PUT')
        @include('schedules.form')
        <div class="form-actions">
            <button class="btn" type="submit">Actualizar horario</button>
            <a class="btn secondary" href="{{ route('schedules.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
