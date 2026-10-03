@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Observaciones del docente · {{ $course->name }}</h1>
        <p class="page-subtitle">{{ $course->teacher?->full_name ? 'Profesor: '.$course->teacher->full_name : '' }} · Se muestran en la planilla de inscripción de cada alumno</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('courses.grades.index', $course) }}">Volver a evaluaciones</a>
    </div>
</div>

<form method="POST" action="{{ route('courses.observations.update', $course) }}" class="card">
    @csrf
    @method('PUT')
    @forelse($students as $student)
        <div class="mt-2">
            <label for="observation-{{ $student->id }}"><strong>{{ $student->full_name }}</strong>{{ $student->school_grade ? ' · '.$student->school_grade : '' }}{{ $student->school_section ? ' '.$student->school_section : '' }}</label>
            <textarea id="observation-{{ $student->id }}" name="observations[{{ $student->id }}]" rows="3">{{ old('observations.'.$student->id, $student->teacher_observations) }}</textarea>
        </div>
    @empty
        <div class="empty-state-inline">Este curso no tiene alumnos con inscripción activa.</div>
    @endforelse
    @if($students->isNotEmpty())
        <div class="form-actions mt-2">
            <button class="btn" type="submit">Guardar observaciones</button>
        </div>
    @endif
</form>
@endsection
