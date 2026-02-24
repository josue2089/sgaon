@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Alumnos 👨‍🎓</h1>
        <p class="page-subtitle">Gestiona todos los estudiantes</p>
    </div>
    <a class="btn" href="{{ route('students.create') }}">Nuevo Alumno</a>
</div>

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['icon' => '👥', 'label' => 'Total Alumnos', 'value' => $summary['total']])
    @include('partials.ui.soft-kpi', ['icon' => '✅', 'label' => 'Activos', 'value' => $summary['active'], 'valueClass' => 'value-ok'])
    @include('partials.ui.soft-kpi', ['icon' => '⚠️', 'label' => 'Inactivos', 'value' => $summary['inactive'], 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['icon' => '📈', 'label' => 'Asist. Promedio', 'value' => is_null($summary['attendance_rate']) ? 'N/D' : $summary['attendance_rate'].'%', 'valueClass' => 'value-purple'])
</div>

<form method="GET" action="{{ route('students.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre o email...">
        </div>
        <select name="level">
            <option value="">Todos los niveles</option>
            @foreach($levels as $level)
                <option value="{{ $level->code }}" @selected($filters['level'] === $level->code)>{{ $level->code }} - {{ $level->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Todos los estados</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
            <option value="withdrawn" @selected($filters['status'] === 'withdrawn')>Retirados</option>
            <option value="graduated" @selected($filters['status'] === 'graduated')>Graduados</option>
        </select>
        <select name="payment_status">
            <option value="">Estado de pago</option>
            <option value="paid" @selected($filters['payment_status'] === 'paid')>Al día</option>
            <option value="pending" @selected($filters['payment_status'] === 'pending')>Pendiente</option>
            <option value="overdue" @selected($filters['payment_status'] === 'overdue')>En mora</option>
            <option value="no_charges" @selected($filters['payment_status'] === 'no_charges')>Sin cargos</option>
        </select>
        <button class="btn secondary" type="submit">Filtros</button>
    </div>
</form>

@if($students->count() === 0)
    <div class="card empty-state">No hay alumnos para los filtros seleccionados.</div>
@else
    <div class="entity-grid">
        @foreach($students as $student)
            @php
                $level = optional(optional($student->enrollments->first())->group)->course?->level;
                $levelCode = $level?->code ?: ($level ? strtoupper(substr((string) $level->name, 0, 2)) : 'N/A');
                $studentPaymentStatus = $paymentStatusByStudent[$student->id] ?? 'no_charges';
                $paymentBadge = match($studentPaymentStatus) {
                    'paid' => ['ok', 'Al día'],
                    'pending' => ['warn', 'Pendiente'],
                    'overdue' => ['danger', 'En mora'],
                    default => ['info', 'Sin cargos'],
                };
            @endphp
            <div class="entity-card entity-card--airy">
                <div class="entity-card-top">
                    <span class="entity-avatar">{{ strtoupper(substr($student->first_name, 0, 1)) }}</span>
                    @include('partials.ui.status-badge', ['tone' => 'level', 'text' => $levelCode])
                </div>
                <div class="entity-spacer"></div>
                <div class="entity-title">{{ $student->full_name }}</div>
                <div class="entity-sub">{{ $student->email ?: 'Sin email' }}</div>
                <div class="entity-sub">{{ $student->campus->name ?? 'Sin sede' }}</div>
                <div class="entity-sub">Asistencia: {{ isset($attendanceByStudent[$student->id]) ? ((int) $attendanceByStudent[$student->id]).'%' : 'N/D' }}</div>
                <div class="entity-bottom">
                    <div class="entity-status-stack">
                        @include('partials.ui.status-badge', ['tone' => $student->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($student->status)])
                        @include('partials.ui.status-badge', ['tone' => $paymentBadge[0], 'text' => $paymentBadge[1]])
                    </div>
                    <a href="{{ route('students.edit', $student) }}">Editar</a>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if($students->hasPages())
    <div class="card">{{ $students->links() }}</div>
@endif
@endsection
