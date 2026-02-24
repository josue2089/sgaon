@extends('layouts.app')
@section('content')
<div class="module-head dashboard-head">
    <div>
        <h1 class="page-title">¡Bienvenido! 👋</h1>
        <p class="page-subtitle">{{ now()->locale('es')->isoFormat('dddd, D [de] MMMM YYYY') }}</p>
    </div>
    <div class="soft-kpi-grid dashboard-achievements">
        @include('partials.ui.soft-kpi', ['label' => 'Mejor Asistencia', 'value' => is_null($bestGroupRate) ? 'N/D' : $bestGroupRate.'%', 'subtitle' => $bestGroup?->name ? 'Grupo '.$bestGroup->name : 'Sin data'])
        @include('partials.ui.soft-kpi', ['label' => 'Pagos del Mes', 'value' => is_null($paymentsRate) ? 'N/D' : $paymentsRate.'%', 'subtitle' => '$'.number_format($paymentsMonthAmount, 2).' recaudado'])
        @include('partials.ui.soft-kpi', ['label' => 'Satisfacción', 'value' => 'N/D', 'subtitle' => 'Sin módulo de encuestas'])
    </div>
</div>

<div class="soft-kpi-grid dashboard-quick-grid">
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'icon' => '👥', 'label' => 'Alumnos', 'value' => $studentsCount])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'icon' => '🎓', 'label' => 'Profesores', 'value' => $teachersCount])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'icon' => '📚', 'label' => 'Cursos', 'value' => $coursesCount])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'icon' => '✅', 'label' => 'Asistencia', 'value' => is_null($attendanceRate) ? 'N/D' : $attendanceRate.'%'])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'icon' => '💳', 'label' => 'Pagos Mes', 'value' => '$'.number_format($paymentsMonthAmount, 0)])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'icon' => '🚨', 'label' => 'Alertas', 'value' => $openAlertsCount])
</div>

<div class="split-2">
    <div class="card">
        <div class="module-head">
            <div>
                <h2 class="section-title">Clases de Hoy 📚</h2>
                <p class="page-subtitle section-subtitle">{{ $todaySessions->count() }} sesiones programadas</p>
            </div>
            @include('partials.ui.status-badge', ['tone' => 'info', 'text' => 'Hoy'])
        </div>

        <div class="activity-list">
            @forelse($todaySessions as $session)
                <div class="session-item">
                    <div class="session-main">
                        <div class="session-title">{{ $session->group->name ?? 'Grupo sin nombre' }} - {{ $session->group->course->name ?? 'Curso sin asignar' }}</div>
                        <div class="entity-sub">{{ $session->group->teacher->full_name ?? 'Profesor sin asignar' }}</div>
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
            <h2 class="section-title">Actividad 🔔</h2>
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
@endsection
