@extends('layouts.app')
@section('content')
{{-- Vista previa del CSV del plan de clases. #470 --}}
<div class="module-head">
    <div>
        <h1 class="page-title">Vista previa del plan · {{ $level->name }}</h1>
        <p class="page-subtitle">{{ count($rows) }} clase(s) listas para importar en {{ $program->name }}.</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('program-levels.show', $level) }}">Cancelar</a>
    </div>
</div>

@if($rowErrors !== [])
    <div class="card">
        <h2 class="section-title">Filas que no se importan</h2>
        <ul>
            @foreach($rowErrors as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card table-card">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Clase</th><th>Unidad</th><th>Contenido</th><th>Tipo</th><th>Acción</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['class_number'] }}</td>
                    <td>{{ $row['unit'] ?? '—' }}</td>
                    <td>{{ $row['content'] }}</td>
                    <td>{{ \App\Support\LessonTemplates::TYPES[$row['lesson_type']] ?? '—' }}</td>
                    <td>{{ in_array($row['class_number'], $existing) ? 'Reemplaza la clase actual' : 'Nueva' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-inline">No hay filas válidas para importar.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($rows !== [])
    <form method="POST" action="{{ route('program-level-lessons.import.store', $level) }}" class="form-actions">
        @csrf
        <button class="btn" type="submit">Importar {{ count($rows) }} clase(s)</button>
    </form>
@endif
@endsection
