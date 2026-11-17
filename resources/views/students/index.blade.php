@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Alumnos</h1>
        <p class="page-subtitle">Gestiona todos los estudiantes</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        @if(\Illuminate\Support\Facades\Route::has('students.export'))
            <a class="btn secondary" href="{{ route('students.export', request()->query()) }}">Exportar CSV</a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('students.import'))
            <a class="btn secondary" href="{{ route('students.import') }}">Importar</a>
        @endif
        <a class="btn secondary" href="{{ route('portal-access.index') }}">Accesos al portal</a>
        <a class="btn" href="{{ route('students.create') }}">Nuevo Alumno</a>
    </div>
</div>
@include('partials.layout.students-tabs')

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'users', 'label' => 'Total Alumnos', 'value' => $summary['total']])
    @include('partials.ui.soft-kpi', ['iconName' => 'check', 'label' => 'Activos', 'value' => $summary['active'], 'valueClass' => 'value-ok'])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Inactivos', 'value' => $summary['inactive'], 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Asist. Promedio', 'value' => is_null($summary['attendance_rate']) ? 'N/D' : $summary['attendance_rate'].'%', 'valueClass' => 'value-purple'])
</div>

@if(($filters['enrollment'] ?? '') === 'none')
    <div class="flash info">
        Mostrando alumnos activos sin inscripción en ningún curso. Inscríbelos o pásalos a histórico.
        <a href="{{ route('students.index') }}" class="ml-2 font-bold">Quitar filtro</a>
    </div>
@endif
<form method="GET" action="{{ route('students.index') }}" class="card">
    @if(($filters['enrollment'] ?? '') !== '')<input type="hidden" name="enrollment" value="{{ $filters['enrollment'] }}">@endif
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre, email, cédula o representante...">
        </div>
        <select name="level">
            <option value="">Todos los niveles</option>
            @foreach($levels as $level)
                <option value="{{ $level->code }}" @selected($filters['level'] === $level->code)>{{ $level->code }} - {{ $level->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Solo activos (predeterminado)</option>
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
        @if(($canFilterByCampus ?? false) && ($campuses ?? collect())->count() > 1)
            <select name="campus_id">
                <option value="">Todas las sedes</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) ($filters['campus_id'] ?? '') === (string) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        @endif
        {{-- #450: programa, curso y período de la inscripción activa. --}}
        <select name="program_id" aria-label="Programa">
            <option value="">Todos los programas</option>
            @foreach($programs as $program)
                <option value="{{ $program->id }}" @selected($filters['program_id'] === (string) $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
        <select name="course_id" aria-label="Curso">
            <option value="">Todos los cursos</option>
            @foreach($courses as $course)
                <option value="{{ $course->id }}" @selected($filters['course_id'] === (string) $course->id)>{{ $course->name }}</option>
            @endforeach
        </select>
        <select name="period_id" aria-label="Período">
            <option value="">Todos los períodos</option>
            @foreach($periods->unique('code') as $period)
                <option value="{{ $period->id }}" @selected($filters['period_id'] === (string) $period->id)>{{ $period->code }}</option>
            @endforeach
        </select>
        <button class="btn secondary" type="submit">Filtros</button>
        @if(collect($filters)->except('enrollment')->filter(fn ($value) => $value !== '')->isNotEmpty())
            <a class="btn secondary" href="{{ route('students.index') }}">Quitar filtros</a>
        @endif
    </div>
</form>

@if($students->count() === 0)
    <div class="card empty-state">No hay alumnos para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        {{-- #450: acciones sobre los alumnos marcados. Las casillas se asocian con form="students-bulk-form". --}}
        <form method="POST" action="{{ route('students.bulk') }}" id="students-bulk-form" class="bulk-void-bar students-bulk-bar" data-bulk-form>
            @csrf
            <span class="table-sub"><strong data-bulk-count>0</strong> seleccionado(s)</span>
            <button class="btn secondary" type="submit" formaction="{{ route('students.export') }}" formmethod="GET" data-bulk-submit disabled>Exportar selección</button>
            <button class="btn secondary" type="submit" name="action" value="reminder" data-bulk-submit disabled
                    data-confirm-title="Enviar recordatorio de pago" data-confirm-ok="Enviar" data-confirm="Se enviará por correo, al alumno y a sus representantes, el recordatorio de cada cuota vencida o que vence en los próximos 7 días.">Enviar recordatorio de pago</button>
            <span class="students-bulk-historical">
                <select name="historical_status" aria-label="Estado al pasar a histórico">
                    <option value="inactive">Inactivo</option>
                    <option value="graduated">Graduado</option>
                    <option value="withdrawn">Retirado</option>
                </select>
                <button class="btn secondary" type="submit" name="action" value="historical" data-bulk-submit disabled
                        data-confirm-title="Pasar a histórico" data-confirm-ok="Pasar a histórico" data-confirm="Los alumnos marcados dejarán de aparecer en el listado de activos.">Pasar a histórico</button>
            </span>
            @error('ids')<div class="form-error">{{ $message }}</div>@enderror
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th class="bulk-col" scope="col"><input type="checkbox" data-bulk-all="students-bulk-form" aria-label="Seleccionar todos los alumnos de esta página"></th>
                    <th scope="col">Alumno</th>
                    <th scope="col">Email</th>
                    <th scope="col">Sede</th>
                    <th scope="col">Nivel</th>
                    <th scope="col">Asistencia</th>
                    <th scope="col">Pago</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="sr-only">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
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
                    <tr>
                        <td class="bulk-col"><input type="checkbox" name="ids[]" value="{{ $student->id }}" form="students-bulk-form" data-bulk-item aria-label="Seleccionar a {{ $student->full_name }}"></td>
                        <td>
                            <div class="table-user">
                                <span class="table-avatar">
                                    @if($student->profile_photo_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($student->profile_photo_path) }}" alt="{{ $student->full_name }}">
                                    @else
                                        {{ strtoupper(substr($student->first_name, 0, 1)) }}
                                    @endif
                                </span>
                                <div>
                                    <div class="table-title">{{ $student->full_name }}</div>
                                    <div class="table-sub">{{ $student->document_id ?: 'Sin documento' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $student->email ?: 'Sin email' }}</td>
                        <td>{{ $student->campus->name ?? 'Sin sede' }}</td>
                        <td>{{ $levelCode }}</td>
                        <td>{{ isset($attendanceByStudent[$student->id]) ? ((int) $attendanceByStudent[$student->id]).'%' : 'N/D' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $paymentBadge[0], 'text' => $paymentBadge[1]])</td>
                        <td>@include('partials.ui.status', ['status' => $student->status])</td>
                        <td class="table-actions">
                            <a href="{{ route('students.show', $student) }}">Detalle</a>
                            @if(\Illuminate\Support\Facades\Route::has('students.move-to-historical'))
                                <button type="button" class="btn-link" data-modal-open="move-historical-modal"
                                        data-form-action="{{ route('students.move-to-historical', $student) }}" data-fill-name="{{ $student->full_name }}">
                                    Mover a histórico
                                </button>
                            @endif
                            @if(auth()->user()?->isMasterAdmin())
                                <form method="POST" action="{{ route('students.destroy', $student) }}" class="inline-form"
                                      data-confirm-title="Eliminar alumno" data-confirm-ok="Eliminar permanentemente" data-confirm-danger
                                      data-confirm="Vas a eliminar permanentemente a {{ $student->full_name }} y toda su información: inscripciones, asistencia, cargos, pagos, representantes vinculados y documentos. Esta acción no se puede deshacer.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-link-danger">Eliminar</button>
                                </form>
                            @endif
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

@if(\Illuminate\Support\Facades\Route::has('students.move-to-historical'))
    {{-- #453: modal único (x-ui.modal); cada fila fija el destino con data-form-action. --}}
    <x-ui.modal id="move-historical-modal" title="Mover a histórico" subtitle="El alumno dejará de aparecer en el listado de activos.">
        <form method="POST" action="#" id="move-historical-form" class="form-grid">
            @csrf
            <p>Alumno: <strong data-fill-name>—</strong></p>
            <div>
                <label for="move-historical-status">Nuevo estado</label>
                <select id="move-historical-status" name="status" required>
                    <option value="inactive">Inactivo</option>
                    <option value="graduated">Graduado</option>
                    <option value="withdrawn">Retirado</option>
                </select>
            </div>
        </form>
        <x-slot:footer>
            <button class="btn secondary" type="button" data-modal-close>Cancelar</button>
            <button class="btn" type="submit" form="move-historical-form">Confirmar</button>
        </x-slot:footer>
    </x-ui.modal>
@endif
@endsection
