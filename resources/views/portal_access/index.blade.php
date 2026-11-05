@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Accesos al portal</h1>
        <p class="page-subtitle">Invita a representantes y alumnos a entrar con su usuario y contraseña.</p>
    </div>
    <a class="btn secondary" href="{{ route('students.index') }}">Volver a alumnos</a>
</div>

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'label' => 'Se pueden invitar', 'value' => $summary['invitable']])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'label' => 'Con acceso activo', 'value' => $summary['active']])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'label' => 'Faltan datos', 'value' => array_sum($summary['blocked'])])
</div>

@if($summary['blocked'] !== [])
    <div class="card">
        <h2 class="section-title section-title-md">Por qué no se puede invitar a algunas personas</h2>
        <ul>
            @foreach($summary['blocked'] as $reason => $count)
                <li><strong>{{ $count }}</strong> · {{ $reason }}</li>
            @endforeach
        </ul>
        <p class="entity-sub">Se corrige completando el correo o la cédula en la ficha del alumno. Pre-Primary y Primary no aparecen aquí: entran por su representante.</p>
    </div>
@endif

<form method="GET" class="card fi-filter-bar">
    @if($campuses->count() > 1)
        <div>
            <label for="campus_id">Sede</label>
            <select id="campus_id" name="campus_id">
                <option value="">Todas</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((int) ($filters['campus_id'] ?? 0) === (int) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label for="program_id">Programa</label>
        <select id="program_id" name="program_id">
            <option value="">Todos</option>
            @foreach($programs as $program)
                <option value="{{ $program->id }}" @selected((int) ($filters['program_id'] ?? 0) === (int) $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="course_id">Curso</label>
        <select id="course_id" name="course_id">
            <option value="">Todos</option>
            @foreach($courses as $course)
                <option value="{{ $course->id }}" @selected((int) ($filters['course_id'] ?? 0) === (int) $course->id)>{{ $course->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="state">Estado</label>
        <select id="state" name="state">
            <option value="">Todos</option>
            @foreach($states as $value => $label)
                <option value="{{ $value }}" @selected(($filters['state'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn secondary" type="submit">Filtrar</button>
</form>

@php($toneByState = ['none' => 'info', 'invited' => 'warn', 'expired' => 'danger', 'active' => 'ok', 'disabled' => 'danger'])
<form method="POST" action="{{ route('portal-access.invite') }}" class="card table-card" data-guard-submit data-confirm="¿Enviar las invitaciones a las personas seleccionadas?">
    @csrf
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Personas ({{ $people->total() }})</h2>
        <div>
            <label class="checkbox-inline"><input type="checkbox" data-select-all-people> Seleccionar esta página</label>
            <button class="btn" type="submit">Invitar seleccionados</button>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th scope="col"><span class="sr-only">Seleccionar</span></th><th scope="col">Persona</th><th scope="col">Usuario</th><th scope="col">Estado</th><th scope="col">Observación</th></tr></thead>
            <tbody>
            @forelse($people as $row)
                <tr>
                    <td>
                        @if($row['selectable'])
                            <input type="checkbox" name="people[]" value="{{ $row['key'] }}" aria-label="Seleccionar a {{ $row['name'] }}">
                        @endif
                    </td>
                    <td>
                        <div class="table-title">{{ $row['name'] }}</div>
                        <div class="table-sub">{{ $row['title'] }}@if($row['students'] !== []) de {{ implode(', ', $row['students']) }}@endif</div>
                    </td>
                    <td>{{ $row['login'] ?: '—' }}</td>
                    <td>@include('partials.ui.status-badge', ['tone' => $toneByState[$row['state']], 'text' => $row['state_label']])</td>
                    <td class="table-sub">{{ $row['blocker'] ?: '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-inline">No hay personas con estos filtros.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</form>
{{ $people->links() }}

@push('scripts')
<script>
document.querySelector('[data-select-all-people]')?.addEventListener('change', function (event) {
    document.querySelectorAll('input[name="people[]"]').forEach(function (box) { box.checked = event.target.checked; });
});
</script>
@endpush
@endsection
