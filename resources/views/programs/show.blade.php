@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">{{ $program->name }}</h1>
        <p class="page-subtitle">Gestiona niveles editables y plantillas por clase del programa.</p>
    </div>
    <div class="form-actions">
        <a class="btn" href="{{ route('program-levels.create', $program) }}">Nuevo nivel</a>
        <a class="btn secondary" href="{{ route('programs.edit', $program) }}">Editar programa</a>
        <a class="btn secondary" href="{{ route('programs.index') }}">Volver</a>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <h2 class="section-title">Ficha del programa</h2>
        <div class="detail-list">
            <div><strong>Nombre:</strong> {{ $program->name }}</div>
            <div><strong>Código:</strong> {{ $program->code }}</div>
            <div><strong>Estatus:</strong> {{ ucfirst($program->status) }}</div>
            <div><strong>Descripción:</strong> {{ $program->description ?: 'Sin descripción' }}</div>
            <div><strong>Precio base por defecto:</strong>
                @if($program->base_price_eur)
                    {{ \App\Support\MoneyFormat::eur($program->base_price_eur) }}
                @else
                    Sin definir
                @endif
            </div>
        </div>
    </div>
    <div class="card">
        <h2 class="section-title">Lectura rápida</h2>
        <div class="detail-list">
            <div><strong>Niveles:</strong> {{ $program->levels->count() }}</div>
            <div><strong>Horas por nivel:</strong> 40 por defecto</div>
            <div><strong>Plantilla base:</strong> 20 clases editables por nivel</div>
            <div><strong>Adaptación:</strong> automática al horario real del curso</div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title">Niveles del programa</h2>
        <div class="entity-sub">{{ $program->levels->count() }} nivel(es)</div>
    </div>
    @if($program->levels->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Orden</th>
                    <th>Nivel</th>
                    <th>Código</th>
                    <th>Horas</th>
                    <th>Clases base</th>
                    <th>Cursos</th>
                    <th>Estatus</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($program->levels as $level)
                    <tr>
                        <td>{{ $level->sort_order }}/{{ $level->program_total }}</td>
                        <td>
                            <div class="table-title">{{ $level->name }}</div>
                            <div class="table-sub">{{ $level->description ?: 'Sin descripción' }}</div>
                        </td>
                        <td>{{ $level->code }}</td>
                        <td>{{ $level->academic_hours }}</td>
                        <td>{{ $level->lessons_count }}</td>
                        <td>{{ $level->courses_count }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $level->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($level->status)])</td>
                        <td class="table-actions">
                            <a href="{{ route('program-levels.show', $level) }}">Ver clases</a>
                            <a href="{{ route('program-levels.edit', $level) }}">Editar</a>
                            <form method="POST" action="{{ route('program-levels.destroy', $level) }}" onsubmit="return confirm('¿Eliminar este nivel?');">
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
        <div class="empty-state">Este programa todavía no tiene niveles cargados.</div>
    @endif
</div>
@endsection
