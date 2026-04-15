@extends('layouts.app')
@section('content')
<div class="module-head teacher-calendar-head">
    <div>
        <h1 class="page-title">Calendario de profesores</h1>
        <p class="page-subtitle">
            @if($mode === 'day')
                Vista diaria de ocupación docente para {{ $selectedDay['label'] }}.
            @else
                Vista semanal de ocupación docente del {{ $weekStart->format('d/m/Y') }} al {{ $weekEnd->format('d/m/Y') }}.
            @endif
        </p>
    </div>
    <div class="teacher-calendar-toolbar">
        <div class="teacher-calendar-mode-switch">
            <a class="btn {{ $mode === 'day' ? '' : 'secondary' }}" href="{{ route('teachers.calendar', ['mode' => 'day', 'date' => $selectedDate->toDateString()]) }}">Día</a>
            <a class="btn {{ $mode === 'week' ? '' : 'secondary' }}" href="{{ route('teachers.calendar', ['mode' => 'week', 'date' => $selectedDate->toDateString()]) }}">Semana</a>
        </div>
        <div class="teacher-calendar-nav">
            @if($mode === 'day')
                <a class="btn secondary" href="{{ route('teachers.calendar', ['mode' => 'day', 'date' => $selectedDate->copy()->subDay()->toDateString()]) }}">&larr;</a>
                <div class="teacher-calendar-current-date">{{ $selectedDate->translatedFormat('l, d \d\e F Y') }}</div>
                <a class="btn secondary" href="{{ route('teachers.calendar', ['mode' => 'day', 'date' => $selectedDate->copy()->addDay()->toDateString()]) }}">&rarr;</a>
                <a class="btn secondary" href="{{ route('teachers.calendar', ['mode' => 'day', 'date' => now()->toDateString()]) }}">Hoy</a>
            @else
                <a class="btn secondary" href="{{ route('teachers.calendar', ['mode' => 'week', 'date' => $selectedDate->copy()->subWeek()->toDateString()]) }}">Semana anterior</a>
                <div class="teacher-calendar-current-date">{{ $weekStart->format('d/m') }} - {{ $weekEnd->format('d/m/Y') }}</div>
                <a class="btn secondary" href="{{ route('teachers.calendar', ['mode' => 'week', 'date' => $selectedDate->copy()->addWeek()->toDateString()]) }}">Semana siguiente</a>
                <a class="btn secondary" href="{{ route('teachers.calendar', ['mode' => 'week', 'date' => now()->toDateString()]) }}">Semana actual</a>
            @endif
        </div>
    </div>
</div>

@if($teachers->count() === 0)
    <div class="card empty-state">No hay profesores activos para construir el calendario general.</div>
@else
    @if($mode === 'day')
        <div class="soft-kpi-grid teacher-calendar-kpis">
            <div class="soft-kpi-card">
                <div class="soft-kpi-label">Bloques del día</div>
                <div class="soft-kpi-value">{{ $summary['slots'] }}</div>
            </div>
            <div class="soft-kpi-card">
                <div class="soft-kpi-label">Ocupados</div>
                <div class="soft-kpi-value">{{ $summary['occupied'] }}</div>
            </div>
            <div class="soft-kpi-card">
                <div class="soft-kpi-label">Disponibles</div>
                <div class="soft-kpi-value">{{ $summary['available'] }}</div>
            </div>
            <div class="soft-kpi-card">
                <div class="soft-kpi-label">Conflictos</div>
                <div class="soft-kpi-value">{{ $summary['conflicts'] }}</div>
            </div>
        </div>

        <div class="teacher-calendar-day-strip card table-card">
            @foreach($weekDays as $day)
                <a href="{{ route('teachers.calendar', ['mode' => 'day', 'date' => $day['date']->toDateString()]) }}" class="teacher-calendar-day-pill {{ $day['date']->isSameDay($selectedDate) ? 'is-active' : '' }} {{ $day['is_today'] ? 'is-today' : '' }}">
                    <span>{{ $day['short_label'] }}</span>
                    <strong>{{ $day['date']->format('d/m') }}</strong>
                </a>
            @endforeach
        </div>

        <div class="card table-card teacher-master-calendar teacher-master-calendar--day">
            <div class="section-head">
                <h2 class="section-title">{{ $selectedDay['label'] }}</h2>
                <div class="entity-sub">Filas por hora, columnas por profesor. Se ocultan los bloques que no aplican a este día.</div>
            </div>
            @if($dayRows->isEmpty())
                <div class="empty-state">No hay horarios configurados para este día.</div>
            @else
                <div class="teacher-calendar-wrap">
                    <table class="teacher-calendar-table teacher-calendar-table--master teacher-calendar-table--day">
                        <thead>
                        <tr>
                            <th>Hora</th>
                            @foreach($teachers as $teacher)
                                <th>{{ $teacher->full_name }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($dayRows as $row)
                            <tr>
                                <td class="teacher-calendar-slot">
                                    <div class="table-title">{{ $row['time_label'] }}</div>
                                </td>
                                @foreach($row['cells'] as $cell)
                                    <td class="teacher-calendar-cell {{ $cell['conflict'] ? 'is-conflict' : ($cell['occupied'] ? 'is-occupied' : 'is-available') }}">
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
                                            <a class="teacher-calendar-link" href="{{ route('courses.show', $session->group?->course_id) }}">Ver curso</a>
                                        @else
                                            <div class="teacher-calendar-badge teacher-calendar-badge--available">Disponible</div>
                                            <div class="teacher-calendar-sub">Bloque libre</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        @foreach($calendarDays as $day)
            <div class="card table-card teacher-master-calendar teacher-master-calendar--week">
                <div class="section-head">
                    <h2 class="section-title">{{ $day['label'] }}</h2>
                    <div class="entity-sub">Vista semanal secundaria por día.</div>
                </div>
                @if($day['rows']->isEmpty())
                    <div class="empty-state">No hay horarios configurados para este día.</div>
                @else
                    <div class="teacher-calendar-wrap">
                        <table class="teacher-calendar-table teacher-calendar-table--master">
                            <thead>
                            <tr>
                                <th>Hora</th>
                                @foreach($teachers as $teacher)
                                    <th>{{ $teacher->full_name }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($day['rows'] as $row)
                                <tr>
                                    <td class="teacher-calendar-slot">
                                        <div class="table-title">{{ $row['time_label'] }}</div>
                                    </td>
                                    @foreach($row['cells'] as $cell)
                                        <td class="teacher-calendar-cell {{ $cell['conflict'] ? 'is-conflict' : ($cell['occupied'] ? 'is-occupied' : 'is-available') }}">
                                            @if($cell['conflict'])
                                                <div class="teacher-calendar-badge teacher-calendar-badge--conflict">Conflicto</div>
                                            @elseif($cell['occupied'])
                                                @php($session = $cell['sessions']->first())
                                                <div class="teacher-calendar-badge">Ocupado</div>
                                                <div class="teacher-calendar-title">{{ $session->group?->course?->name ?? 'Curso asignado' }}</div>
                                            @else
                                                <div class="teacher-calendar-badge teacher-calendar-badge--available">Disponible</div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
@endif
@endsection
