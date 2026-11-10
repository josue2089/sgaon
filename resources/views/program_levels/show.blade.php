@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">{{ $level->name }}</h1>
        <p class="page-subtitle">Plantilla base por clase para el programa {{ $program->name }}.</p>
    </div>
    <div class="form-actions">
        <form method="POST" action="{{ route('program-levels.duplicate', $level) }}">
            @csrf
            <button class="btn secondary" type="submit">Duplicar nivel</button>
        </form>
        <a class="btn" href="{{ route('program-level-lessons.create', $level) }}">Nueva clase base</a>
        <a class="btn secondary" href="{{ route('programs.show', $program) }}">Volver</a>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <h2 class="section-title">Ficha del nivel</h2>
        <div class="detail-list">
            <div><strong>Programa:</strong> {{ $program->name }}</div>
            <div><strong>Código:</strong> {{ $level->code }}</div>
            <div><strong>Orden:</strong> {{ $level->sort_order }}/{{ $level->program_total }}</div>
            <div><strong>Horas:</strong> {{ $level->academic_hours }}</div>
            <div><strong>Precio base:</strong>
                @if($level->base_price_eur)
                    {{ \App\Support\MoneyFormat::eur($level->base_price_eur) }}
                @elseif($level->resolvedBasePriceEur())
                    {{ \App\Support\MoneyFormat::eur($level->resolvedBasePriceEur()) }} <span class="entity-sub">(heredado del programa)</span>
                @else
                    Sin definir
                @endif
            </div>
            <div><strong>Recordatorio:</strong> {{ $level->reminder_days_before }} días</div>
            <div><strong>Estado:</strong> {{ \App\Support\StatusLabel::label($level->status) }}</div>
        </div>
    </div>
    <div class="card">
        <h2 class="section-title">Lectura rápida</h2>
        <div class="detail-list">
            <div><strong>Clases base:</strong> {{ $level->lessons->count() }}</div>
            <div><strong>Uso esperado:</strong> se adapta al número real de sesiones del curso</div>
            <div><strong>Compresión:</strong> el sistema agrupa clases si hay menos sesiones reales</div>
            <div><strong>Expansión:</strong> repite/refuerza contenido si hay más sesiones reales</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="section-head">
        <h2 class="section-title">Importar plan de clases (CSV)</h2>
        <div class="entity-sub">Columnas: clase; unidad; contenido; tipo. Primero verás una vista previa.</div>
    </div>
    <form method="POST" action="{{ route('program-level-lessons.import.preview', $level) }}" enctype="multipart/form-data" class="fi-filter-bar">
        @csrf
        <div>
            <label for="lessons-csv">Archivo CSV</label>
            <input id="lessons-csv" type="file" name="file" accept=".csv,text/csv,text/plain" required>
            @error('file')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button class="btn secondary" type="submit">Ver vista previa</button>
    </form>
    <div class="form-hint">Tipos: {{ implode(', ', array_keys(\App\Support\LessonTemplates::TYPES)) }}. Una clase con el mismo número se reemplaza; las demás no se tocan.</div>
</div>

<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title">Clases base</h2>
        <div class="entity-sub">{{ $level->lessons->count() }} clase(s) configuradas</div>
    </div>
    @if($level->lessons->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Clase</th>
                    <th>Unidad</th>
                    <th>Tipo</th>
                    <th>Contenido</th>
                    <th>Orden</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($level->lessons as $lesson)
                    <tr>
                        <td>{{ $lesson->class_number }}</td>
                        <td>{{ $lesson->unit ?: 'N/D' }}</td>
                        <td>
                            {{ $lesson->typeLabel() ?? '—' }}
                            @if($lesson->activity_tags)
                                <div class="table-sub">{{ collect($lesson->activity_tags)->map(fn ($tag) => \App\Support\LessonTemplates::TAGS[$tag] ?? $tag)->implode(' · ') }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="table-title">{{ $lesson->content }}</div>
                            <div class="table-sub">{{ $lesson->notes ?: 'Sin nota' }}</div>
                        </td>
                        <td>{{ $lesson->sort_order }}</td>
                        <td class="table-actions">
                            <a href="{{ route('program-level-lessons.edit', $lesson) }}">Editar</a>
                            <form method="POST" action="{{ route('program-level-lessons.destroy', $lesson) }}" data-confirm="¿Eliminar esta clase base?" data-confirm-danger>
                                @csrf
                                @method('DELETE')
                                <button class="btn-link-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">Este nivel todavía no tiene clases base.</div>
    @endif
</div>
@endsection
