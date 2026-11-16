@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nueva evaluación · {{ $course->name }}</h1>
        <p class="page-subtitle">Puedes guardar a medias y completar después. Las familias la ven solo cuando la publicas.</p>
    </div>
    <a class="btn secondary" href="{{ route('courses.grades.index', $course) }}">Cancelar</a>
</div>

@if($enrollments->isEmpty())
    <div class="card"><div class="empty-state-inline">No hay alumnos activos en este curso.</div></div>
@else
<form method="POST" action="{{ route('courses.grades.store', $course) }}" class="grades-matrix-form">
    @csrf
    <div class="card grades-meta-card">
        <div class="grid-2">
            <div>
                <label for="evaluated_on">Fecha del informe</label>
                <input id="evaluated_on" type="date" name="evaluated_on" value="{{ old('evaluated_on', now()->toDateString()) }}" required>
            </div>
            <div>
                <label for="title">Título opcional</label>
                <input id="title" type="text" name="title" value="{{ old('title') }}" placeholder="Ej. Reporte octubre">
            </div>
        </div>
    </div>

    @include('grades.partials.entry-cards')

    <div class="grades-matrix-bar">
        <button class="btn secondary" type="submit">Guardar borrador</button>
        <button class="btn" type="submit" name="publish" value="1">Guardar y publicar</button>
    </div>
</form>
@endif
@endsection
