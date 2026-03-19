@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar nivel</h1>
        <p class="page-subtitle">Ajusta nombre, código y orden del nivel</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('academic-levels.update', $level) }}">
        @csrf
        @method('PUT')
        @include('academic_levels.form')
        <div class="form-actions">
            <button class="btn" type="submit">Actualizar nivel</button>
            <a class="btn secondary" href="{{ route('academic-levels.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
