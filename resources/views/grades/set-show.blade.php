@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">{{ $set->title ?: 'Evaluación '.$set->evaluated_on->format('d/m/Y') }}</h1>
        <p class="page-subtitle">{{ $set->course?->name }} · {{ $set->evaluated_on->format('d/m/Y') }}</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('courses.grades.index', $set->course) }}">Historial del curso</a>
        <a class="btn {{ $set->isPublished() ? '' : 'secondary' }}" href="{{ route('grade-evaluation-sets.edit', $set) }}">{{ $set->isPublished() ? 'Editar' : 'Seguir cargando' }}</a>
    </div>
</div>

{{-- #447: borrador o publicada, y cuánto falta. --}}
<div class="card grades-publish-card">
    <div class="grades-publish-state">
        @if($set->isPublished())
            @include('partials.ui.status-badge', ['tone' => 'ok', 'text' => 'Publicada'])
            <span class="table-sub">Visible para alumnos y representantes desde el {{ $set->published_at->format('d/m/Y H:i') }}{{ $set->publisher ? ' · '.$set->publisher->name : '' }}.</span>
        @else
            @include('partials.ui.status-badge', ['tone' => 'warn', 'text' => 'Borrador'])
            <span class="table-sub">Las familias todavía no la ven.</span>
        @endif
        <span class="table-sub">{{ $completeCount }} de {{ $roster->count() }} alumno(s) con los cinco rubros.</span>
    </div>
    @error('publish')<div class="form-error">{{ $message }}</div>@enderror
    @if($set->isPublished())
        <form method="POST" action="{{ route('grade-evaluation-sets.unpublish', $set) }}" onsubmit="return confirm('Las familias dejarán de ver esta evaluación. ¿Continuar?')">
            @csrf
            @method('DELETE')
            <button class="btn secondary" type="submit">Volver a borrador</button>
        </form>
    @else
        <form method="POST" action="{{ route('grade-evaluation-sets.publish', $set) }}" @if($completeCount < $roster->count()) onsubmit="return confirm('Hay alumnos sin las cinco notas. Las familias verán solo lo cargado. ¿Publicar igual?')" @endif>
            @csrf
            <button class="btn" type="submit">Publicar a las familias</button>
        </form>
    @endif
</div>

<div class="card grades-detail-card">
    <div class="grades-detail-list">
        @foreach($roster as $item)
            @php($entry = $item['entry'])
            <article class="grades-entry-card">
                <div class="grades-entry-head">
                    <div class="table-title">{{ $item['enrollment']->student?->full_name ?? 'Alumno' }}</div>
                </div>
                @if(! $entry)
                    <div class="table-sub">Sin notas cargadas.</div>
                    </article>
                    @continue
                @endif
                <div class="grades-entry-rubrics">
                    @foreach($skillKeys as $skill)
                        @php($rating = $entry->ratingForSkill($skill))
                        <div class="grades-entry-rubric">
                            <span class="grades-entry-label">{{ $skillLabels[$skill] }}</span>
                            @include('partials.ui.status-badge', ['tone' => \App\Support\GradeRubric::ratingTone($rating), 'text' => \App\Support\GradeRubric::RATING_LABELS_ES[$rating] ?? 'Sin nota'])
                        </div>
                    @endforeach
                </div>
                @if($entry->observations)
                    <div class="grades-entry-obs"><strong>Observaciones:</strong> {{ $entry->observations }}</div>
                @endif
            </article>
        @endforeach
    </div>
</div>
@endsection
