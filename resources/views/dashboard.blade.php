@extends('layouts.app')
@section('content')
<div class="module-head dashboard-head">
    <div>
        <h1 class="page-title">¡Bienvenido!</h1>
        <p class="page-subtitle">{{ now()->locale('es')->isoFormat('dddd, D [de] MMMM YYYY') }}</p>
    </div>
    <div class="soft-kpi-grid dashboard-achievements">
        @include('partials.ui.soft-kpi', ['label' => 'Mejor Asistencia', 'value' => is_null($bestGroupRate) ? 'N/D' : $bestGroupRate.'%', 'subtitle' => $bestGroup?->name ? 'Grupo '.$bestGroup->name : 'Sin data'])
        @include('partials.ui.soft-kpi', ['label' => 'Pagos del Mes', 'value' => is_null($paymentsRate) ? 'N/D' : $paymentsRate.'%', 'subtitle' => '$'.number_format($paymentsMonthAmount, 2).' recaudado'])
        @include('partials.ui.soft-kpi', ['label' => 'Satisfacción', 'value' => 'N/D', 'subtitle' => 'Sin módulo de encuestas'])
    </div>
</div>

<div class="soft-kpi-grid dashboard-quick-grid">
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'iconName' => 'users', 'label' => 'Alumnos', 'value' => $studentsCount, 'subtitle' => auth()->user()?->role === 'teacher' ? 'En tus cursos' : (auth()->user()?->isMasterAdmin() ? 'Todas las sedes' : null)])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'iconName' => 'teacher', 'label' => 'Profesores', 'value' => $teachersCount, 'subtitle' => auth()->user()?->isMasterAdmin() ? 'Todas las sedes' : null])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'iconName' => 'book', 'label' => 'Cursos', 'value' => $coursesCount])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'iconName' => 'check', 'label' => 'Asistencia', 'value' => is_null($attendanceRate) ? 'N/D' : $attendanceRate.'%'])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'iconName' => 'payment', 'label' => 'Pagos Mes', 'value' => '$'.number_format($paymentsMonthAmount, 0)])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'iconName' => 'alert', 'label' => 'Alertas', 'value' => $openAlertsCount])
</div>

@if(auth()->user()?->role === 'teacher' && $teacherGradeCourses->isNotEmpty())
<div class="card grades-dashboard-card">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Evaluaciones por curso</h2>
        <p class="page-subtitle section-subtitle">Carga y consulta el historial de notas por rubro</p>
    </div>
    <div class="grades-dashboard-links">
        @foreach($teacherGradeCourses as $tc)
            <a class="grades-dashboard-link" href="{{ route('courses.grades.index', $tc) }}">
                <span class="grades-dashboard-link-title">{{ $tc->name }}</span>
                @if($tc->code)<span class="grades-dashboard-link-meta">{{ $tc->code }}</span>@endif
                <span class="grades-dashboard-link-arrow">Evaluaciones →</span>
            </a>
        @endforeach
    </div>
</div>
@endif

<div class="split-2">
    <div class="card">
        <div class="module-head">
            <div>
                <h2 class="section-title">Clases del día</h2>
                <p class="page-subtitle section-subtitle">{{ $selectedDate->locale('es')->isoFormat('dddd, D [de] MMMM YYYY') }} · {{ $todaySessions->count() }} sesiones</p>
            </div>
            <form method="GET" action="{{ route('dashboard') }}" class="dashboard-date-filter">
                <a class="date-nav-btn" href="{{ route('dashboard', ['date' => $previousDate->toDateString()]) }}">←</a>
                <input type="date" name="date" value="{{ $selectedDate->toDateString() }}">
                <button class="date-nav-btn" type="submit">Ir</button>
                <a class="date-nav-btn" href="{{ route('dashboard', ['date' => now()->toDateString()]) }}">Hoy</a>
                <a class="date-nav-btn" href="{{ route('dashboard', ['date' => $nextDate->toDateString()]) }}">→</a>
            </form>
        </div>

        <div class="activity-list">
            <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:.5rem;margin-bottom:1rem;">
                @foreach($weekDays as $day)
                    <a href="{{ route('dashboard', ['date' => $day['date']->toDateString()]) }}" style="text-decoration:none;border:2px solid var(--line);border-radius:14px;padding:.45rem .4rem;background:{{ $day['selected'] ? '#eef4ff' : '#fff' }};text-align:center;color:var(--text);">
                        <div style="font-weight:800;font-size:.82rem;">{{ strtoupper($day['date']->locale('es')->isoFormat('ddd')) }}</div>
                        <div style="font-size:.78rem;">{{ $day['date']->format('d/m') }}</div>
                        <div style="font-weight:900;margin-top:.15rem;">{{ $day['count'] }}</div>
                    </a>
                @endforeach
            </div>
            @forelse($todaySessions as $session)
                <div class="session-item">
                    <div class="session-main">
                        <div class="session-title">{{ $session->group->name ?? 'Grupo sin nombre' }} - {{ $session->group->course->name ?? 'Curso sin asignar' }}</div>
                        <div class="entity-sub">{{ $session->group->teacher->full_name ?? 'Profesor sin asignar' }}</div>
                        @if($session->group?->course)
                            <div class="entity-sub"><a href="{{ route('courses.show', $session->group->course) }}">Ver detalle del curso</a></div>
                        @endif
                    </div>
                    <div class="session-meta">
                        <div class="session-time">{{ $session->starts_at ? \Illuminate\Support\Str::of($session->starts_at)->substr(0,5) : '--:--' }}</div>
                        <div class="entity-sub">{{ $session->students_count }} estudiantes</div>
                    </div>
                </div>
            @empty
                <div class="empty-state-inline">No hay sesiones programadas para hoy.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="module-head">
            <h2 class="section-title">Actividad</h2>
            @include('partials.ui.status-badge', ['tone' => 'info', 'text' => 'En vivo'])
        </div>
        <div class="activity-list">
            @forelse($openAlerts->take(5) as $alert)
                <div class="activity-item">
                    <div>
                        <div class="activity-title">{{ ucfirst($alert->type) }}</div>
                        <div class="entity-sub">{{ $alert->message }}</div>
                    </div>
                    @include('partials.ui.status-badge', ['tone' => $alert->type === 'overdue' ? 'danger' : 'warn', 'text' => '!'])
                </div>
            @empty
                <div class="empty-state-inline">Sin alertas abiertas.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="card" style="margin-top:1rem;">
    <div class="module-head">
        <div>
            <h2 class="section-title">KPIs Académicos</h2>
            <p class="page-subtitle section-subtitle">Asistencia real por nivel, docente y grupo</p>
        </div>
    </div>
    <div class="split-3">
        <div>
            <h3 class="section-title section-title-sm">Por nivel</h3>
            <div class="stack-sm">
                @forelse($attendanceByLevel as $item)
                    <div class="activity-item">
                        <div>
                            <div class="activity-title">{{ $item->label }}</div>
                            <div class="entity-sub">{{ (int) $item->total }} registros</div>
                        </div>
                        @include('partials.ui.status-badge', ['tone' => 'info', 'text' => (is_null($item->rate) ? 'N/D' : ((int) $item->rate).'%')])
                    </div>
                @empty
                    <div class="empty-state-inline">N/D</div>
                @endforelse
            </div>
        </div>
        <div>
            <h3 class="section-title section-title-sm">Por docente</h3>
            <div class="stack-sm">
                @forelse($attendanceByTeacher as $item)
                    <div class="activity-item">
                        <div>
                            <div class="activity-title">{{ $item->label }}</div>
                            <div class="entity-sub">{{ (int) $item->total }} registros</div>
                        </div>
                        @include('partials.ui.status-badge', ['tone' => 'info', 'text' => (is_null($item->rate) ? 'N/D' : ((int) $item->rate).'%')])
                    </div>
                @empty
                    <div class="empty-state-inline">N/D</div>
                @endforelse
            </div>
        </div>
        <div>
            <h3 class="section-title section-title-sm">Por grupo</h3>
            <div class="stack-sm">
                @forelse($attendanceByGroup as $item)
                    <div class="activity-item">
                        <div>
                            <div class="activity-title">{{ $item->label }}</div>
                            <div class="entity-sub">{{ (int) $item->total }} registros</div>
                        </div>
                        @include('partials.ui.status-badge', ['tone' => 'info', 'text' => (is_null($item->rate) ? 'N/D' : ((int) $item->rate).'%')])
                    </div>
                @empty
                    <div class="empty-state-inline">N/D</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
