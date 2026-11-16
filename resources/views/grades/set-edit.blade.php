@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar evaluación</h1>
        <p class="page-subtitle">{{ $set->course?->name }} · {{ $set->evaluated_on->format('d/m/Y') }} · {{ $set->isPublished() ? 'Publicada' : 'Borrador' }}</p>
    </div>
    <a class="btn secondary" href="{{ route('grade-evaluation-sets.show', $set) }}">Volver</a>
</div>

<form method="POST" action="{{ route('grade-evaluation-sets.update', $set) }}" class="grades-matrix-form">
    @csrf
    @method('PUT')
    <div class="card grades-meta-card">
        <div class="grid-2">
            <div>
                <label for="evaluated_on">Fecha del informe</label>
                <input id="evaluated_on" type="date" name="evaluated_on" value="{{ old('evaluated_on', $set->evaluated_on->toDateString()) }}" required>
            </div>
            <div>
                <label for="title">Título opcional</label>
                <input id="title" type="text" name="title" value="{{ old('title', $set->title) }}" placeholder="Ej. Reporte octubre">
            </div>
        </div>
    </div>

    @include('grades.partials.entry-cards')

    <div class="grades-matrix-bar">
        @if($set->isPublished())
            <span class="table-sub grades-bar-note">Publicada: los cambios se ven de inmediato en el portal.</span>
            <button class="btn" type="submit">Guardar cambios</button>
        @else
            <button class="btn secondary" type="submit">Guardar borrador</button>
            <button class="btn" type="submit" name="publish" value="1">Guardar y publicar</button>
        @endif
    </div>
</form>
@endsection
