@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar evaluación</h1>
        <p class="page-subtitle">{{ $set->course?->name }} · {{ $set->evaluated_on->format('d/m/Y') }}</p>
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
            @foreach($set->entries as $entry)
                <tr>
                    <td class="grades-matrix-sticky-col">
                        <div class="table-title">{{ $entry->enrollment?->student?->full_name ?? 'Alumno' }}</div>
                    </td>
                    @foreach($skillColumns as $skill => $column)
                        <td>
                            <select name="entries[{{ $entry->enrollment_id }}][{{ $column }}]" class="grades-matrix-select" required>
                                @foreach($ratingOptions as $val)
                                    <option value="{{ $val }}" @selected(old('entries.'.$entry->enrollment_id.'.'.$column, $entry->{$column}) === $val)>{{ $ratingLabels[$val] }}</option>
                                @endforeach
                            </select>
                        </td>
                    @endforeach
                    <td>
                        <textarea name="entries[{{ $entry->enrollment_id }}][observations]" rows="2" class="grades-matrix-notes">{{ old('entries.'.$entry->enrollment_id.'.observations', $entry->observations) }}</textarea>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="grades-matrix-bar">
        <button class="btn" type="submit">Guardar cambios</button>
    </div>
</form>
@endsection
