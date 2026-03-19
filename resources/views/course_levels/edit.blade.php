@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar escala</h1>
        <p class="page-subtitle">Actualiza posición, CEFR y recordatorio del nivel</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('course-levels.update', $level) }}">
        @csrf
        @method('PUT')
        @include('course_levels.form')
        <div class="form-actions">
            <button class="btn" type="submit">Actualizar escala</button>
            <a class="btn secondary" href="{{ route('course-levels.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
