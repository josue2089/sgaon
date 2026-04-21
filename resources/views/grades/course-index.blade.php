@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Evaluaciones · {{ $course->name }}</h1>
        <p class="page-subtitle">{{ $course->teacher?->full_name ? 'Profesor: '.$course->teacher->full_name : '' }}</p>
    </div>
    <div class="form-actions">
        @if(auth()->user()?->role === 'admin')
            <a class="btn secondary" href="{{ route('courses.show', $course) }}">Volver al curso</a>
        @else
            <a class="btn secondary" href="{{ route('dashboard') }}">Dashboard</a>
        @endif
        <a class="btn" href="{{ route('courses.grades.create', $course) }}">Nueva evaluación</a>
    </div>
</div>

<div class="card">
    @forelse($sets as $set)
        <article class="grades-set-card">
            <div class="grades-set-card-head">
                <div>
                    <div class="table-title">{{ $set->title ?: 'Informe del '.$set->evaluated_on->format('d/m/Y') }}</div>
                    <div class="table-sub">{{ $set->evaluated_on->format('d/m/Y') }} · {{ $set->entries_count }} alumno(s)</div>
                </div>
                <div class="grades-set-card-actions">
                    <a class="btn secondary" href="{{ route('grade-evaluation-sets.show', $set) }}">Ver detalle</a>
                    <a class="btn secondary" href="{{ route('grade-evaluation-sets.edit', $set) }}">Editar</a>
                </div>
            </div>
        </article>
    @empty
        <div class="empty-state-inline">Aún no hay evaluaciones registradas para este curso.</div>
    @endforelse
</div>

@if($sets->hasPages())
    <div class="pagination-wrap">{{ $sets->links() }}</div>
@endif
@endsection
