@extends('layouts.app')
@section('content')
<div class="module-head dashboard-head">
    <div>
        <h1 class="page-title">¡Bienvenido! 👋</h1>
        <p class="page-subtitle">{{ now()->locale('es')->isoFormat('dddd, D [de] MMMM YYYY') }}</p>
    </div>
    <div class="soft-kpi-grid dashboard-achievements">
        <div class="soft-kpi">
            <div class="label">Mejor Asistencia</div>
            <div class="value">{{ is_null($bestGroupRate) ? 'N/D' : $bestGroupRate.'%' }}</div>
            <div class="entity-sub">{{ $bestGroup?->name ? 'Grupo '.$bestGroup->name : 'Sin data' }}</div>
        </div>
        <div class="soft-kpi">
            <div class="label">Pagos del Mes</div>
            <div class="value">{{ is_null($paymentsRate) ? 'N/D' : $paymentsRate.'%' }}</div>
            <div class="entity-sub">${{ number_format($paymentsMonthAmount, 2) }} recaudado</div>
        </div>
        <div class="soft-kpi">
            <div class="label">Satisfacción</div>
            <div class="value">N/D</div>
            <div class="entity-sub">Sin módulo de encuestas</div>
        </div>
    </div>
</div>

<div class="soft-kpi-grid">
    <div class="soft-kpi" style="text-align:center;">
        <div style="font-size:2.1rem;">👥</div>
        <div class="label" style="margin-top:.3rem;">Alumnos</div>
        <div class="value">{{ $studentsCount }}</div>
    </div>
    <div class="soft-kpi" style="text-align:center;">
        <div style="font-size:2.1rem;">🎓</div>
        <div class="label" style="margin-top:.3rem;">Profesores</div>
        <div class="value">{{ $teachersCount }}</div>
    </div>
    <div class="soft-kpi" style="text-align:center;">
        <div style="font-size:2.1rem;">📚</div>
        <div class="label" style="margin-top:.3rem;">Cursos</div>
        <div class="value">{{ $coursesCount }}</div>
    </div>
    <div class="soft-kpi" style="text-align:center;">
        <div style="font-size:2.1rem;">✅</div>
        <div class="label" style="margin-top:.3rem;">Asistencia</div>
        <div class="value">{{ is_null($attendanceRate) ? 'N/D' : $attendanceRate.'%' }}</div>
    </div>
    <div class="soft-kpi" style="text-align:center;">
        <div style="font-size:2.1rem;">💳</div>
        <div class="label" style="margin-top:.3rem;">Pagos Mes</div>
        <div class="value">${{ number_format($paymentsMonthAmount, 0) }}</div>
    </div>
    <div class="soft-kpi" style="text-align:center;">
        <div style="font-size:2.1rem;">🚨</div>
        <div class="label" style="margin-top:.3rem;">Alertas</div>
        <div class="value">{{ $openAlertsCount }}</div>
    </div>
</div>

<div class="split-2">
    <div class="card">
        <div class="module-head">
            <div>
                <h2 class="section-title">Clases de Hoy 📚</h2>
                <p class="page-subtitle" style="font-size:1rem;">{{ $todaySessions->count() }} sesiones programadas</p>
            </div>
            <span class="badge-pill badge-info">Hoy</span>
        </div>

        <div style="display:grid; gap:.8rem;">
            @forelse($todaySessions as $session)
                <div style="border:2px solid #dbe6fb; border-radius:20px; padding:1rem; background:linear-gradient(90deg, #f6f9ff, #fbf7ff);">
                    <div style="display:flex; justify-content:space-between; gap:1rem; align-items:center;">
                        <div>
                            <div style="font-size:1.25rem; font-weight:900; color:#0a1e5e;">{{ $session->group->name ?? 'Grupo sin nombre' }} - {{ $session->group->course->name ?? 'Curso sin asignar' }}</div>
                            <div class="entity-sub">{{ $session->group->teacher->full_name ?? 'Profesor sin asignar' }}</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:1.45rem; font-weight:900; color:#1d4ed8;">{{ $session->starts_at ? \Illuminate\Support\Str::of($session->starts_at)->substr(0,5) : '--:--' }}</div>
                            <div class="entity-sub">{{ $session->students_count }} estudiantes</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="entity-sub">No hay sesiones programadas para hoy.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="module-head">
            <h2 class="section-title">Actividad 🔔</h2>
            <span class="badge-pill badge-info">En vivo</span>
        </div>
        <div style="display:grid; gap:.8rem;">
            @forelse($openAlerts->take(5) as $alert)
                <div style="display:flex; justify-content:space-between; gap:.5rem; border-bottom:1px solid #e4ebfb; padding-bottom:.55rem;">
                    <div>
                        <div style="font-weight:900; color:#0a1e5e;">{{ ucfirst($alert->type) }}</div>
                        <div class="entity-sub">{{ $alert->message }}</div>
                    </div>
                    <span class="badge-pill {{ $alert->type === 'overdue' ? 'badge-danger' : 'badge-warn' }}">!</span>
                </div>
            @empty
                <div class="entity-sub">Sin alertas abiertas.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
