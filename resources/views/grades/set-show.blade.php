@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">{{ $set->title ?: 'Evaluación '.$set->evaluated_on->format('d/m/Y') }}</h1>
        <p class="page-subtitle">{{ $set->course?->name }} · {{ $set->evaluated_on->format('d/m/Y') }}</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('courses.grades.index', $set->course) }}">Historial del curso</a>
        <a class="btn" href="{{ route('grade-evaluation-sets.edit', $set) }}">Editar</a>
    </div>
</div>

<div class="card grades-detail-card">
    <div class="grades-detail-list">
        @foreach($set->entries as $entry)
            <article class="grades-entry-card">
                <div class="grades-entry-head">
                    <div class="table-title">{{ $entry->enrollment?->student?->full_name ?? 'Alumno' }}</div>
                </div>
                <div class="grades-entry-rubrics">
                    @foreach($skillKeys as $skill)
                        @php($rating = $entry->ratingForSkill($skill))
                        <div class="grades-entry-rubric">
                            <span class="grades-entry-label">{{ $skillLabels[$skill] }}</span>
                            @include('partials.ui.status-badge', ['tone' => \App\Support\GradeRubric::ratingTone($rating), 'text' => \App\Support\GradeRubric::RATING_LABELS_ES[$rating] ?? $rating])
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
