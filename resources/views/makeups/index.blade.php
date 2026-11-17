@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Recuperativas</h1>
        <p class="page-subtitle">Solicitudes, validación de pago y bloques recuperativos</p>
    </div>
</div>

<div class="soft-kpi-grid soft-kpi-grid-5">
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Pend. validación', 'value' => $summary['pending_validation']])
    @include('partials.ui.soft-kpi', ['iconName' => 'calendar', 'label' => 'Aprobadas', 'value' => $summary['approved_for_booking']])
    @include('partials.ui.soft-kpi', ['iconName' => 'calendar', 'label' => 'Reservadas', 'value' => $summary['booked']])
    @include('partials.ui.soft-kpi', ['iconName' => 'check', 'label' => 'Completadas', 'value' => $summary['completed']])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'No atendidas', 'value' => $summary['missed']])
</div>

<form method="GET" action="{{ route('makeups.index') }}" class="card">
    <div class="fi-filter-bar">
        <select name="status">
            <option value="">Todos los estados</option>
            @foreach(\App\Models\MakeupRequest::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @include('partials.ui.student-select', ['students' => $students, 'name' => 'student_id', 'id' => 'makeup-student-filter', 'fieldLabel' => 'Alumno', 'placeholder' => 'Todos los alumnos', 'selected' => $filters['student_id'] ?? '', 'remote' => true])
        <select name="program_id" id="makeup-program-filter">
            <option value="">Todos los programas</option>
            @foreach($programs as $program)
                <option value="{{ $program->id }}" @selected((int) ($filters['program_id'] ?? 0) === (int) $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
        <select name="program_level_id" data-level-for="makeup-program-filter">
            <option value="">Todos los niveles</option>
            @foreach($programLevels as $level)
                <option value="{{ $level->id }}" data-program-id="{{ $level->program_id }}" @selected((int) ($filters['program_level_id'] ?? 0) === (int) $level->id)>{{ $level->name }}</option>
            @endforeach
        </select>
        <input type="date" name="session_date" value="{{ $filters['session_date'] ?? '' }}">
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

{{-- #452: bloques en una tabla con próximos/anteriores; crear y editar en modales; cancelar avisa a los reservados. --}}
@php($sessionTab = fn (string $scope) => route('makeups.index', array_merge(request()->except(['sessions', 'page']), ['sessions' => $scope])))
<div class="card table-card">
    <div class="module-head">
        <div>
            <h3 class="section-title section-title-sm">Bloques recuperativos</h3>
            <nav class="section-tabs" aria-label="Bloques">
                <a href="{{ $sessionTab('upcoming') }}" @class(['section-tab', 'is-active' => $sessionsScope === 'upcoming'])>Próximos</a>
                <a href="{{ $sessionTab('past') }}" @class(['section-tab', 'is-active' => $sessionsScope === 'past'])>Anteriores (60 días)</a>
            </nav>
        </div>
        <button class="btn" type="button" data-modal-open="makeup-session-create">Nuevo bloque</button>
    </div>
    @error('session')<div class="form-error">{{ $message }}</div>@enderror
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr><th scope="col">Fecha</th><th scope="col">Horario</th><th scope="col">Profesor</th><th scope="col">Programa</th><th scope="col">Nivel</th><th scope="col">Cupos</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr>
            </thead>
            <tbody>
            @forelse($makeupSessions as $session)
                @php($editable = ! in_array($session->status, ['cancelled', 'completed'], true))
                <tr>
                    <td>{{ $session->session_date?->locale('es')->isoFormat('ddd D/MM/YYYY') ?? 'N/D' }}</td>
                    <td>{{ substr((string) $session->starts_at, 0, 5) }} - {{ substr((string) $session->ends_at, 0, 5) }}</td>
                    <td>{{ $session->teacher?->full_name ?? 'N/D' }}</td>
                    <td>{{ $session->program?->name ?? 'N/D' }}</td>
                    <td>{{ $session->programLevel?->name ?? 'N/D' }}</td>
                    <td>{{ $session->booked_count }}/{{ $session->capacity }}</td>
                    <td>@include('partials.ui.status', ['status' => $session->status, 'domain' => 'booking'])</td>
                    <td class="table-actions">
                        @if($editable)
                            <button class="btn-link" type="button" data-modal-open="makeup-session-edit"
                                    data-session-edit="{{ json_encode([
                                        'action' => route('makeups.sessions.update', $session),
                                        'teacher_id' => $session->teacher_id,
                                        'program_id' => $session->program_id,
                                        'program_level_id' => $session->program_level_id,
                                        'session_date' => $session->session_date?->toDateString(),
                                        'starts_at' => substr((string) $session->starts_at, 0, 5),
                                        'ends_at' => substr((string) $session->ends_at, 0, 5),
                                        'capacity' => $session->capacity,
                                        'notes' => $session->notes,
                                        'booked' => $session->booked_count,
                                    ]) }}">Editar</button>
                            <form method="POST" action="{{ route('makeups.sessions.cancel', $session) }}"
                                  data-confirm-title="Cancelar bloque" data-confirm-danger data-confirm-ok="Cancelar bloque" data-confirm-reason="Motivo (se incluye en la nota del bloque)"
                                  data-confirm="{{ $session->booked_count > 0 ? 'Este bloque tiene '.$session->booked_count.' reserva(s). Se liberarán y se avisará por correo a cada alumno para que reserve otro horario.' : 'El bloque dejará de estar disponible para reservar.' }}">
                                @csrf
                                <button class="btn-link-danger" type="submit">Cancelar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state-inline">{{ $sessionsScope === 'past' ? 'No hubo bloques en los últimos 60 días.' : 'No hay bloques próximos. Crea uno con "Nuevo bloque".' }}</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@php($sessionFields = function (string $prefix) use ($teachers, $programs, $programLevels) { return compact('prefix', 'teachers', 'programs', 'programLevels'); })
<x-ui.modal id="makeup-session-create" title="Nuevo bloque recuperativo" size="md"
            :data-open-on-load="($errors->any() && old('form') === 'create') ? '1' : null">
    <form method="POST" action="{{ route('makeups.sessions.store') }}" class="stack-sm" id="makeup-session-create-form">
        @csrf
        <input type="hidden" name="form" value="create">
        <input type="hidden" name="status" value="open">
        @include('makeups.partials.session-fields', $sessionFields('create') + ['values' => old('form') === 'create' ? old() : ['capacity' => 8]])
    </form>
    <x-slot:footer>
        <button class="btn secondary" type="button" data-modal-close>Cerrar</button>
        <button class="btn" type="submit" form="makeup-session-create-form">Crear bloque</button>
    </x-slot:footer>
</x-ui.modal>

<x-ui.modal id="makeup-session-edit" title="Editar bloque recuperativo" size="md"
            subtitle="Si cambias la fecha o la hora, se avisa por correo a quienes lo tienen reservado.">
    <form method="POST" action="#" class="stack-sm" id="makeup-session-edit-form" data-session-edit-form>
        @csrf
        @method('PUT')
        @include('makeups.partials.session-fields', $sessionFields('edit') + ['values' => []])
        <p class="table-sub" data-session-booked></p>
    </form>
    <x-slot:footer>
        <button class="btn secondary" type="button" data-modal-close>Cerrar</button>
        <button class="btn" type="submit" form="makeup-session-edit-form">Guardar cambios</button>
    </x-slot:footer>
</x-ui.modal>

<script>
    (() => {
        // Nivel dependiente del programa: <select data-level-for="id-del-select-de-programa">.
        const syncLevels = (levelSelect) => {
            const program = document.getElementById(levelSelect.dataset.levelFor);
            if (!program) return;
            [...levelSelect.options].forEach((option) => {
                const hide = option.value !== '' && program.value !== '' && option.dataset.programId !== program.value;
                option.hidden = hide;
                option.disabled = hide;
                if (hide && option.selected) levelSelect.value = '';
            });
        };
        const levelSelects = [...document.querySelectorAll('select[data-level-for]')];
        levelSelects.forEach((levelSelect) => {
            document.getElementById(levelSelect.dataset.levelFor)?.addEventListener('change', () => syncLevels(levelSelect));
            syncLevels(levelSelect);
        });

        // Editar: un solo modal que se llena con los datos del bloque.
        const form = document.querySelector('[data-session-edit-form]');
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-session-edit]');
            if (!button || !form) return;
            const data = JSON.parse(button.dataset.sessionEdit);
            form.action = data.action;
            ['teacher_id', 'program_id', 'session_date', 'starts_at', 'ends_at', 'capacity', 'notes'].forEach((field) => {
                const input = form.querySelector(`[name="${field}"]`);
                if (input) input.value = data[field] ?? '';
            });
            levelSelects.forEach(syncLevels);
            form.querySelector('[name="program_level_id"]').value = data.program_level_id ?? '';
            form.querySelector('[name="capacity"]').min = Math.max(1, data.booked);
            form.querySelector('[data-session-booked]').textContent = data.booked > 0 ? `Tiene ${data.booked} reserva(s): el cupo no puede ser menor.` : 'Todavía no tiene reservas.';
        });
    })();
</script>

<div class="card">
    <h3 class="section-title section-title-sm">Solicitudes</h3>
    <div class="makeup-admin-list">
        @forelse($requests as $makeupRequest)
            @php($course = $makeupRequest->enrollment?->group?->course)
            <article class="makeup-admin-card">
                <div class="makeup-admin-head">
                    <div>
                        <div class="table-title">{{ $makeupRequest->student?->full_name ?? 'N/D' }}</div>
                        <div class="table-sub">{{ $makeupRequest->student?->email ?? 'Sin email' }}</div>
                    </div>
                    <div>@include('partials.ui.status', ['status' => $makeupRequest->status, 'domain' => 'makeup'])</div>
                </div>

                <div class="makeup-admin-meta">
                    <div><strong>Clase perdida:</strong> {{ $course?->name ?? 'N/D' }} · {{ $makeupRequest->missedSession?->session_date?->format('d/m/Y') ?? 'N/D' }}</div>
                    <div><strong>Programa:</strong> {{ $course?->program?->name ?? 'N/D' }} · {{ $course?->programLevel?->name ?? 'N/D' }}</div>
                    <div><strong>Costo:</strong> {{ \App\Support\MoneyFormat::usd($makeupRequest->price) }}</div>
                    <div><strong>Cargo:</strong>
                        @if($makeupRequest->charge)
                            {{ $makeupRequest->charge->voided_at ? 'Anulado' : \App\Support\StatusLabel::label($makeupRequest->charge->status) }}
                        @else
                            Sin cargo
                        @endif
                    </div>
                </div>

                <div class="grid-3">
                    <div class="makeup-admin-pane">
                        <h4>Evidencias</h4>
                        <div class="table-sub">
                            Pago:
                            @if($makeupRequest->payment_proof)
                                <a href="{{ route('files.makeup-attachment', $makeupRequest->payment_proof) }}" target="_blank" rel="noopener">Ver archivo</a>
                            @else
                                No
                            @endif
                        </div>
                        <div class="table-sub">
                            Reposo:
                            @if($makeupRequest->medical_support_attachment)
                                <a href="{{ route('files.makeup-attachment', $makeupRequest->medical_support_attachment) }}" target="_blank" rel="noopener">Ver archivo</a>
                            @else
                                No
                            @endif
                        </div>
                    </div>

                    <div class="makeup-admin-pane">
                        <h4>Reserva</h4>
                        @if($makeupRequest->booking)
                            <div>{{ $makeupRequest->booking->makeupSession?->session_date?->format('d/m/Y') ?? 'N/D' }}</div>
                            <div class="table-sub">{{ $makeupRequest->booking->makeupSession?->starts_at ?? '' }} - {{ $makeupRequest->booking->makeupSession?->ends_at ?? '' }}</div>
                            <form method="POST" action="{{ route('makeups.bookings.update', $makeupRequest->booking) }}" class="stack-xs">
                                @csrf
                                @method('PATCH')
                                <select name="status">
                                    @foreach(['reserved','attended','missed','cancelled'] as $status)
                                        <option value="{{ $status }}" @selected($makeupRequest->booking->status === $status)>{{ \App\Support\StatusLabel::label($status) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn secondary" type="submit">Actualizar reserva</button>
                            </form>
                        @else
                            <span class="table-sub">Sin reserva</span>
                        @endif
                    </div>

                    <div class="makeup-admin-pane">
                        <h4>Acciones</h4>
                        @if($makeupRequest->status === 'pending_validation')
                            <form method="POST" action="{{ route('makeups.requests.review', $makeupRequest) }}" class="stack-xs">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="approve">
                                <input type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}">
                                <input name="method" placeholder="Método" value="Transferencia">
                                <input name="reference" placeholder="Referencia">
                                <button class="btn secondary" type="submit">Aprobar comprobante</button>
                            </form>
                            <form method="POST" action="{{ route('makeups.requests.review', $makeupRequest) }}" class="stack-xs" style="margin-top:.5rem;">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="reject">
                                <input name="rejection_reason" placeholder="Motivo rechazo">
                                <button class="btn secondary" type="submit">Rechazar comprobante</button>
                            </form>
                        @elseif($makeupRequest->payment?->receipt)
                            <a href="{{ route('finance.receipts.show', $makeupRequest->payment->receipt) }}">Ver recibo</a>
                        @else
                            <a href="{{ route('students.show', $makeupRequest->student_id) }}">Ver alumno</a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state-inline">No hay solicitudes recuperativas.</div>
        @endforelse
    </div>
    @if($requests->hasPages())
        {{ $requests->links() }}
    @endif
</div>
@endsection
