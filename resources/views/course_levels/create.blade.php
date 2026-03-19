@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nueva escala</h1>
        <p class="page-subtitle">Configura la escala secuencial de progresión de cursos</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('course-levels.store') }}">
        @csrf
        @include('course_levels.form')
        <div class="form-actions">
            <button class="btn" type="submit">Guardar escala</button>
            <a class="btn secondary" href="{{ route('course-levels.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
