@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nuevo nivel</h1>
        <p class="page-subtitle">Agrega un nivel editable al programa {{ $program->name }}.</p>
    </div>
    <a class="btn secondary" href="{{ route('programs.show', $program) }}">Volver</a>
</div>

<form method="POST" action="{{ route('program-levels.store', $program) }}" class="card stack-sm">
    @csrf
    @include('program_levels.form')
    <div class="form-actions">
        <button class="btn" type="submit">Guardar nivel</button>
    </div>
</form>
@endsection
