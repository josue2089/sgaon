@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nueva evaluación · {{ $course->name }}</h1>
        <p class="page-subtitle">Completa los cinco rubros por alumno</p>
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

    <div class="grades-matrix-scroll card">
        <table class="grades-matrix-table">
            <thead>
            <tr>
                <th class="grades-matrix-sticky-col">Alumno</th>
                @foreach($skillKeys as $skill)
                    <th>{{ $skillLabels[$skill] }}</th>
                @endforeach
                <th>Observaciones</th>
            </tr>
            </thead>
            <tbody>
            @foreach($enrollments as $enrollment)
                <tr>
                    <td class="grades-matrix-sticky-col">
                        <div class="table-title">{{ $enrollment->student?->full_name ?? 'Alumno #'.$enrollment->student_id }}</div>
                    </td>
                    @foreach($skillColumns as $skill => $column)
                        <td>
                            <label class="sr-only">{{ $skillLabels[$skill] }}</label>
                            <select name="entries[{{ $enrollment->id }}][{{ $column }}]" class="grades-matrix-select" required>
                                <option value="" disabled selected>—</option>
                                @foreach($ratingOptions as $val)
                                    <option value="{{ $val }}" @selected(old('entries.'.$enrollment->id.'.'.$column) === $val)>{{ $ratingLabels[$val] }}</option>
                                @endforeach
                            </select>
                        </td>
                    @endforeach
                    <td>
                        <textarea name="entries[{{ $enrollment->id }}][observations]" rows="2" class="grades-matrix-notes" placeholder="Opcional">{{ old('entries.'.$enrollment->id.'.observations') }}</textarea>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="grades-matrix-bar">
        <button class="btn" type="submit">Guardar evaluación</button>
    </div>
</form>
@endif
@endsection
