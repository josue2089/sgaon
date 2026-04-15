@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar clase base</h1>
        <p class="page-subtitle">Actualiza la clase {{ $lesson->class_number }} del nivel {{ $level->name }}.</p>
    </div>
    <a class="btn secondary" href="{{ route('program-levels.show', $level) }}">Volver</a>
</div>

<form method="POST" action="{{ route('program-level-lessons.update', $lesson) }}" class="card stack-sm">
    @csrf
    @method('PUT')
    @include('program_level_lessons.form')
    <div class="form-actions">
        <button class="btn" type="submit">Actualizar clase base</button>
    </div>
</form>
@endsection
