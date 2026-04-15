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
            <div><strong>Recordatorio:</strong> {{ $level->reminder_days_before }} días</div>
            <div><strong>Estatus:</strong> {{ ucfirst($level->status) }}</div>
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
                            <div class="table-title">{{ $lesson->content }}</div>
                            <div class="table-sub">{{ $lesson->notes ?: 'Sin nota' }}</div>
                        </td>
                        <td>{{ $lesson->sort_order }}</td>
                        <td class="table-actions">
                            <a href="{{ route('program-level-lessons.edit', $lesson) }}">Editar</a>
                            <form method="POST" action="{{ route('program-level-lessons.destroy', $lesson) }}" onsubmit="return confirm('¿Eliminar esta clase base?');">
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
