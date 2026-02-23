@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">¡Bienvenido! 👋</h1>
        <p class="page-subtitle">{{ now()->locale('es')->isoFormat('dddd, D [de] MMMM YYYY') }}</p>
    </div>
    <div class="soft-kpi-grid" style="margin-top:0;">
        <div class="soft-kpi">
            <div class="label">Mejor Asistencia</div>
            <div class="value">95%</div>
            <div class="entity-sub">Grupo B2-M-003</div>
        </div>
        <div class="soft-kpi">
            <div class="label">Pagos del Mes</div>
            <div class="value">92%</div>
            <div class="entity-sub">{{ $studentsCount }} alumnos</div>
        </div>
        <div class="soft-kpi">
            <div class="label">Satisfacción</div>
            <div class="value">4.8/5</div>
            <div class="entity-sub">Promedio general</div>
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
        <div class="value">87%</div>
    </div>
    <div class="soft-kpi" style="text-align:center;">
        <div style="font-size:2.1rem;">💳</div>
        <div class="label" style="margin-top:.3rem;">Pagos</div>
        <div class="value">${{ number_format($pendingCharges, 0) }}</div>
    </div>
    <div class="soft-kpi" style="text-align:center;">
        <div style="font-size:2.1rem;">📊</div>
        <div class="label" style="margin-top:.3rem;">Reportes</div>
        <div class="value">{{ $groupsCount }}</div>
    </div>
</div>

<div class="split-2">
    <div class="card">
        <div class="module-head">
            <div>
                <h2 class="section-title">Clases de Hoy 📚</h2>
                <p class="page-subtitle" style="font-size:1rem;">{{ $groupsCount }} sesiones programadas</p>
            </div>
            <span class="badge-pill badge-info">Hoy</span>
        </div>

        <div style="display:grid; gap:.8rem;">
            @for($i = 1; $i <= 3; $i++)
                <div style="border:2px solid #dbe6fb; border-radius:20px; padding:1rem; background:linear-gradient(90deg, #f6f9ff, #fbf7ff);">
                    <div style="display:flex; justify-content:space-between; gap:1rem; align-items:center;">
                        <div>
                            <div style="font-size:1.25rem; font-weight:900; color:#0a1e5e;">Grupo {{ $i }} - Nivel {{ ['A2','B1','B2'][$i-1] }}</div>
                            <div class="entity-sub">Profesor asignado</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:1.45rem; font-weight:900; color:#1d4ed8;">{{ ['10:00 AM','11:30 AM','2:00 PM'][$i-1] }}</div>
                            <div class="entity-sub">{{ [15,18,8][$i-1] }} estudiantes</div>
                        </div>
                    </div>
                </div>
            @endfor
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
