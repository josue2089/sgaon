@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nuevo nivel</h1>
        <p class="page-subtitle">Configura niveles académicos disponibles por campus</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('academic-levels.store') }}">
        @csrf
        @include('academic_levels.form')
        <div class="form-actions">
            <button class="btn" type="submit">Guardar nivel</button>
            <a class="btn secondary" href="{{ route('academic-levels.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
