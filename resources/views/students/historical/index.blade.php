@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Alumnos Históricos</h1>
        <p class="page-subtitle">Estudiantes inactivos, graduados o retirados</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a class="btn secondary" href="{{ route('students.index') }}">Alumnos activos</a>
        <a class="btn" href="{{ route('students.historical.import') }}">Importar histórico</a>
    </div>
</div>

@if(session('success'))
    <div class="card" style="margin-bottom:1rem;color:#166534;">{{ session('success') }}</div>
@endif

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'users', 'label' => 'Total históricos', 'value' => $summary['total']])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Inactivos', 'value' => $summary['inactive'], 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'check', 'label' => 'Graduados', 'value' => $summary['graduated'], 'valueClass' => 'value-ok'])
    @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Retirados', 'value' => $summary['withdrawn'], 'valueClass' => 'value-purple'])
</div>

<form method="GET" action="{{ route('students.historical.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Nombre, email, documento o contrato...">
        </div>
        <select name="year">
            <option value="">Todos los años</option>
            @foreach($years as $year)
                <option value="{{ $year }}" @selected($filters['year'] === (string) $year)>{{ $year }}</option>
            @endforeach
        </select>
        @if($isMaster)
            <select name="campus_id">
                <option value="">Todas las sedes</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected($filters['campus_id'] === (string) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        @endif
        <select name="status">
            <option value="">Todos los estados</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivo</option>
            <option value="graduated" @selected($filters['status'] === 'graduated')>Graduado</option>
            <option value="withdrawn" @selected($filters['status'] === 'withdrawn')>Retirado</option>
        </select>
        <select name="registration_program_id">
            <option value="">Todos los programas</option>
            @foreach($programs as $program)
                <option value="{{ $program->id }}" @selected($filters['registration_program_id'] === (string) $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
        <button class="btn secondary" type="submit">Filtros</button>
    </div>
</form>

@if($students->count() === 0)
    <div class="card empty-state">No hay alumnos históricos para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Sede</th>
                    <th>Programa</th>
                    <th>Fecha inscripción</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($students as $student)
                    @php
                        $statusLabel = match($student->status) {
                            'inactive' => 'Inactivo',
                            'graduated' => 'Graduado',
                            'withdrawn' => 'Retirado',
                            default => ucfirst($student->status),
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="table-user">
                                <span class="table-avatar">{{ strtoupper(substr($student->first_name, 0, 1)) }}</span>
                                <div>
                                    <div class="table-title">{{ $student->full_name }}</div>
                                    <div class="table-sub">{{ $student->document_id ?: ($student->contract_number ?: 'Sin documento') }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $student->campus->name ?? 'Sin sede' }}</td>
                        <td>{{ $student->registrationProgram->name ?? '—' }}</td>
                        <td>{{ $student->enrollment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => 'warn', 'text' => $statusLabel])</td>
                        <td class="table-actions">
                            <a href="{{ route('students.show', $student) }}">Detalle</a>
                            <form method="POST" action="{{ route('students.historical.activate', $student) }}" style="display:inline;">
                                @csrf
                                <input type="hidden" name="year" value="{{ $filters['year'] }}">
                                <input type="hidden" name="campus_id" value="{{ $filters['campus_id'] }}">
                                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                <input type="hidden" name="registration_program_id" value="{{ $filters['registration_program_id'] }}">
                                <button type="submit" class="btn-link" onclick="return confirm('¿Activar este alumno? Aparecerá en el listado de alumnos activos.')">Activar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($students->hasPages())
    <div class="card">{{ $students->links() }}</div>
@endif
@endsection
