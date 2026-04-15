@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nueva clase base</h1>
        <p class="page-subtitle">Agrega una clase a la plantilla del nivel {{ $level->name }}.</p>
    </div>
    <a class="btn secondary" href="{{ route('program-levels.show', $level) }}">Volver</a>
</div>

<form method="POST" action="{{ route('program-level-lessons.store', $level) }}" class="card stack-sm">
    @csrf
    @include('program_level_lessons.form')
    <div class="form-actions">
        <button class="btn" type="submit">Guardar clase base</button>
    </div>
</form>
@endsection
