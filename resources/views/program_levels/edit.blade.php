@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar nivel</h1>
        <p class="page-subtitle">Actualiza el nivel {{ $level->name }} del programa {{ $program->name }}.</p>
    </div>
    <a class="btn secondary" href="{{ route('program-levels.show', $level) }}">Volver</a>
</div>

<form method="POST" action="{{ route('program-levels.update', $level) }}" class="card stack-sm">
    @csrf
    @method('PUT')
    @include('program_levels.form')
    <div class="form-actions">
        <button class="btn" type="submit">Actualizar nivel</button>
    </div>
</form>
@endsection
