@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Calendario de profesores</h1>
        <p class="page-subtitle">Vista general de horarios ocupados y disponibles por docente para la semana del {{ $weekStart->format('d/m/Y') }} al {{ $weekEnd->format('d/m/Y') }}.</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('teachers.calendar', ['week_start' => $weekStart->copy()->subWeek()->toDateString()]) }}">Semana anterior</a>
        <a class="btn secondary" href="{{ route('teachers.calendar', ['week_start' => now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString()]) }}">Semana actual</a>
        <a class="btn secondary" href="{{ route('teachers.calendar', ['week_start' => $weekStart->copy()->addWeek()->toDateString()]) }}">Semana siguiente</a>
    </div>
</div>

@if($teachers->count() === 0)
    <div class="card empty-state">No hay profesores activos para construir el calendario general.</div>
@else
    @foreach($calendarDays as $day)
        <div class="card table-card teacher-master-calendar">
            <div class="section-head">
                <h2 class="section-title">{{ $day['label'] }}</h2>
                <div class="entity-sub">Columnas por profesor, filas por horario</div>
            </div>
            <div class="teacher-calendar-wrap">
                <table class="teacher-calendar-table teacher-calendar-table--master">
                    <thead>
                    <tr>
                        <th>Horario</th>
                        @foreach($teachers as $teacher)
                            <th>{{ $teacher->full_name }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($day['rows'] as $row)
                        <tr>
                            <td class="teacher-calendar-slot">
                                <div class="table-title">{{ $row['schedule']->compact_label }}</div>
                                <div class="table-sub">{{ $row['schedule']->display_label }}</div>
                            </td>
                            @foreach($row['cells'] as $cell)
                                <td class="teacher-calendar-cell {{ $cell['conflict'] ? 'is-conflict' : ($cell['occupied'] ? 'is-occupied' : ($cell['available'] ? 'is-available' : 'is-off')) }}">
                                    @if($cell['conflict'])
                                        <div class="teacher-calendar-badge teacher-calendar-badge--conflict">Conflicto</div>
                                        @foreach($cell['sessions'] as $session)
                                            <div class="teacher-calendar-title">{{ $session->group?->course?->name ?? 'Curso asignado' }}</div>
                                            <div class="teacher-calendar-sub">{{ $session->planned_class_label ?: ($session->topic ?: 'Sesión planificada') }}</div>
                                        @endforeach
                                    @elseif($cell['occupied'])
                                        @php($session = $cell['sessions']->first())
                                        <div class="teacher-calendar-badge">Ocupado</div>
                                        <div class="teacher-calendar-title">{{ $session->group?->course?->name ?? 'Curso asignado' }}</div>
                                        <div class="teacher-calendar-sub">{{ $session->planned_class_label ?: ($session->topic ?: 'Sesión planificada') }}</div>
                                        <a class="teacher-calendar-link" href="{{ route('teachers.show', $cell['teacher']) }}">Ver profesor</a>
                                    @elseif($cell['available'])
                                        <div class="teacher-calendar-badge teacher-calendar-badge--available">Disponible</div>
                                        <div class="teacher-calendar-sub">Bloque libre</div>
                                    @else
                                        <div class="teacher-calendar-sub">No aplica</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endif
@endsection
