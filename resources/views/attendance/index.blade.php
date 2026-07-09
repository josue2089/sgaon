@extends('layouts.app')
@section('content')
@php
    $present = $records->where('status', 'present')->count();
    $absent = $records->where('status', 'absent')->count();
    $late = $records->where('status', 'late')->count();
    $justified = $records->where('status', 'justified')->count();
    $total = $enrollments->count();
    $registered = $present + $absent + $late + $justified;
    $pending = max(0, $total - $registered);
    $previousPayload = $previousRecords->mapWithKeys(fn ($record, $enrollmentId) => [
        $enrollmentId => [
            'status' => $record->status,
            'notes' => $record->notes,
        ],
    ]);
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Asistencia</h1>
        <p class="page-subtitle">Registra la asistencia por sesión</p>
    </div>
    @if($selectedSession)
        @include('partials.ui.status-badge', ['tone' => $canRecordAttendance ? 'info' : 'warn', 'text' => $selectedSession->session_date?->format('d M Y').($canRecordAttendance ? '' : ' · Programada')])
    @endif
</div>

<div class="metric-grid metric-grid-5">
    @include('partials.ui.metric-card', ['tone' => 'metric-blue', 'iconName' => 'users', 'label' => 'Total', 'value' => $total])
    @include('partials.ui.metric-card', ['tone' => 'metric-green', 'iconName' => 'check', 'label' => 'Presentes', 'value' => $present])
    @include('partials.ui.metric-card', ['tone' => 'metric-red', 'iconName' => 'warning', 'label' => 'Ausentes', 'value' => $absent])
    @include('partials.ui.metric-card', ['tone' => 'metric-orange', 'iconName' => 'trend', 'label' => 'Tarde', 'value' => $late])
    @include('partials.ui.metric-card', ['tone' => 'metric-purple', 'iconName' => 'calendar', 'label' => 'Justificadas', 'value' => $justified, 'subtitle' => $pending > 0 ? $pending.' pendientes' : 'Todos marcados'])
</div>

<div class="card">
    <form method="GET" action="{{ route('attendance.index') }}">
        <div class="fi-filter-bar fi-filter-bar-attendance">
            <div class="search">
                <select name="class_session_id">
                    <option value="">Seleccione sesión</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}" @selected(request('class_session_id')==$session->id)>
                            {{ $session->session_date?->format('Y-m-d') }} - {{ $session->group->name ?? '' }}{{ $session->canRecordAttendance() ? '' : ' (programada)' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="btn" type="submit">Cargar sesión</button>
        </div>
    </form>
</div>

@if($selectedSession)
    <div class="card">
        @unless($canRecordAttendance)
            <div class="flash warn attendance-locked-notice">
                Esta sesión está programada para el {{ $selectedSession->session_date?->format('d/m/Y') }}.
                Podés consultar la ficha, pero la asistencia se habilitará ese día.
            </div>
        @endunless
        <form method="POST" action="{{ route('attendance.store') }}" @class(['attendance-form--locked' => ! $canRecordAttendance])>
            @csrf
            <input type="hidden" name="class_session_id" value="{{ $selectedSession->id }}">
            <fieldset @disabled(! $canRecordAttendance)>
            <div class="attendance-program-card">
                <div class="section-head">
                    <h2 class="section-title">Programa de la sesión</h2>
                    <div class="entity-sub">Indica el contenido visto y si el profesor va al día.</div>
                </div>
                <div class="detail-list detail-list-soft">
                    <div><strong>Clase planificada:</strong> {{ $selectedSession->planned_class_label ?: 'N/D' }}</div>
                    <div><strong>Unidad:</strong> {{ $selectedSession->planned_unit ?: 'N/D' }}</div>
                    <div><strong>Contenido esperado:</strong> {{ $selectedSession->planned_content ?: 'Sin contenido planificado' }}</div>
                </div>
                <div class="grid-3">
                    <div>
                        <label>Programa ejecutado / tema visto</label>
                        <input type="text" name="topic" value="{{ old('topic', $selectedSession->topic ?? '') }}" placeholder="Ej. Verbo to be">
                    </div>
                    <div>
                        <label>Avance del programa</label>
                        <select name="program_status">
                            <option value="">Sin indicar</option>
                            <option value="on_track" @selected(old('program_status', $selectedSession->program_status ?? '') === 'on_track')>Al día</option>
                            <option value="delayed" @selected(old('program_status', $selectedSession->program_status ?? '') === 'delayed')>Con retraso</option>
                        </select>
                    </div>
                    <div>
                        <label>Observación</label>
                        <input type="text" name="program_notes" value="{{ old('program_notes', $selectedSession->program_notes ?? '') }}" placeholder="Observación del avance">
                    </div>
                </div>
            </div>
            <div class="attendance-toolbar">
                <div class="attendance-toolbar-main">
                    <div class="search attendance-search">
                        <input type="text" id="attendance-search" placeholder="Buscar por nombre, cédula o representante...">
                    </div>
                    <div class="attendance-shortcuts">
                        <span class="badge-pill badge-info">Atajos: P / A / T / J</span>
                        @if($previousSession)
                            @include('partials.ui.status-badge', ['tone' => 'info', 'text' => 'Base: '.$previousSession->session_date?->format('d M Y')])
                        @else
                            @include('partials.ui.status-badge', ['tone' => 'warn', 'text' => 'Sin sesión previa'])
                        @endif
                    </div>
                </div>
                <div class="attendance-toolbar-actions">
                    <button class="btn secondary" type="button" data-bulk-status="present">Todos presentes</button>
                    <button class="btn secondary" type="button" data-bulk-status="absent">Todos ausentes</button>
                    <button class="btn secondary" type="button" data-bulk-status="late">Todos tarde</button>
                    <button class="btn secondary" type="button" data-bulk-status="justified">Todos justificadas</button>
                    <button class="btn secondary" type="button" id="btn-copy-last" @disabled(!$previousSession || ! $canRecordAttendance)>Copiar última asistencia</button>
                    @if($canRecordAttendance)
                        <button class="btn" type="submit">Guardar asistencia</button>
                    @endif
                </div>
            </div>
            <div class="attendance-sheet-wrap">
                <table class="attendance-sheet">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Alumno</th>
                        <th>Anterior</th>
                        <th>Marcación rápida</th>
                        <th>Estado</th>
                        <th>Nota</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($enrollments as $i => $enrollment)
                        @php
                            $currentStatus = $records[$enrollment->id]->status ?? 'present';
                            $previousStatus = $previousRecords[$enrollment->id]->status ?? null;
                        @endphp
                        <tr class="attendance-row attendance-row--{{ $currentStatus }}" data-student-name="{{ \App\Support\StudentSearch::haystack($enrollment->student) }}">
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <input type="hidden" name="records[{{ $i }}][enrollment_id]" value="{{ $enrollment->id }}">
                                <div class="attendance-student">
                                    <span class="entity-avatar attendance-avatar">{{ strtoupper(substr($enrollment->student->first_name, 0, 1)) }}</span>
                                    <div>
                                        <div class="attendance-student-name">{{ $enrollment->student->full_name }}</div>
                                        <div class="entity-sub">{{ $enrollment->student->email ?: 'Sin email' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($previousStatus)
                                    <span class="status-pill attendance-pill attendance-pill--{{ $previousStatus }}">{{ ucfirst($previousStatus) }}</span>
                                @else
                                    <span class="entity-sub">N/D</span>
                                @endif
                            </td>
                            <td>
                                <div class="attendance-toggle-group">
                                    <button type="button" class="attendance-toggle attendance-toggle--present" data-status-target="{{ $enrollment->id }}" data-status-value="present">P</button>
                                    <button type="button" class="attendance-toggle attendance-toggle--absent" data-status-target="{{ $enrollment->id }}" data-status-value="absent">A</button>
                                    <button type="button" class="attendance-toggle attendance-toggle--late" data-status-target="{{ $enrollment->id }}" data-status-value="late">T</button>
                                    <button type="button" class="attendance-toggle attendance-toggle--justified" data-status-target="{{ $enrollment->id }}" data-status-value="justified">J</button>
                                </div>
                            </td>
                            <td>
                                <select name="records[{{ $i }}][status]" class="attendance-status" data-enrollment-id="{{ $enrollment->id }}">
                                    @foreach($statuses as $status)
                                        <option value="{{ $status }}" @selected($currentStatus == $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input name="records[{{ $i }}][notes]" class="attendance-notes" data-enrollment-id="{{ $enrollment->id }}" value="{{ $records[$enrollment->id]->notes ?? '' }}" placeholder="Observación opcional">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="attendance-footer">
                <div class="entity-sub">
                    @if($canRecordAttendance)
                        Consejo: usa `P`, `A`, `T`, `J` sobre la fila enfocada para registrar más rápido.
                    @else
                        La marcación estará disponible a partir del {{ $selectedSession->session_date?->format('d/m/Y') }}.
                    @endif
                </div>
                @if($canRecordAttendance)
                    <button class="btn" type="submit">Guardar asistencia</button>
                @endif
            </div>
            </fieldset>
        </form>
    </div>
@endif

@if(!$selectedSession)
    <div class="card empty-state">Selecciona una sesión para cargar alumnos y registrar asistencia.</div>
@endif

@if($selectedSession && $canRecordAttendance)
<script>
    (() => {
        const previous = @json($previousPayload);
        const statusSelects = Array.from(document.querySelectorAll('.attendance-status'));
        const notesInputs = Array.from(document.querySelectorAll('.attendance-notes'));
        const copyLastButton = document.getElementById('btn-copy-last');
        const searchInput = document.getElementById('attendance-search');
        const rows = Array.from(document.querySelectorAll('.attendance-row'));
        const toggleButtons = Array.from(document.querySelectorAll('.attendance-toggle'));
        let activeEnrollmentId = null;

        const syncRowState = (select) => {
            const row = select.closest('.attendance-row');
            if (!row) {
                return;
            }

            row.classList.remove('attendance-row--present', 'attendance-row--absent', 'attendance-row--late', 'attendance-row--justified');
            row.classList.add(`attendance-row--${select.value}`);

            const buttons = row.querySelectorAll('.attendance-toggle');
            buttons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.statusValue === select.value);
            });
        };

        const setStatus = (enrollmentId, status) => {
            const select = document.querySelector(`.attendance-status[data-enrollment-id="${enrollmentId}"]`);
            if (!select) {
                return;
            }

            select.value = status;
            syncRowState(select);
        };

        statusSelects.forEach((select) => {
            syncRowState(select);

            select.addEventListener('change', () => {
                syncRowState(select);
            });
        });

        toggleButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const enrollmentId = button.dataset.statusTarget;
                const status = button.dataset.statusValue;
                activeEnrollmentId = enrollmentId;
                setStatus(enrollmentId, status);
            });
        });

        rows.forEach((row) => {
            row.addEventListener('click', (event) => {
                if (event.target.closest('input, select, button')) {
                    return;
                }

                const select = row.querySelector('.attendance-status');
                activeEnrollmentId = select?.dataset.enrollmentId || null;
                row.classList.add('is-focused');
                rows.filter((item) => item !== row).forEach((item) => item.classList.remove('is-focused'));
            });
        });

        document.querySelectorAll('[data-bulk-status]').forEach((button) => {
            button.addEventListener('click', () => {
                const status = button.dataset.bulkStatus;
                statusSelects.forEach((select) => {
                    select.value = status;
                    syncRowState(select);
                });
            });
        });

        if (copyLastButton) {
            copyLastButton.addEventListener('click', () => {
                statusSelects.forEach((select) => {
                    const enrollmentId = String(select.dataset.enrollmentId || '');
                    const entry = previous[enrollmentId];
                    if (entry && entry.status) {
                        select.value = entry.status;
                        syncRowState(select);
                    }
                });

                notesInputs.forEach((input) => {
                    const enrollmentId = String(input.dataset.enrollmentId || '');
                    const entry = previous[enrollmentId];
                    input.value = entry && typeof entry.notes === 'string' ? entry.notes : '';
                });
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const term = searchInput.value.trim().toLowerCase();
                rows.forEach((row) => {
                    const name = row.dataset.studentName || '';
                    row.classList.toggle('is-hidden', term !== '' && !name.includes(term));
                });
            });
        }

        document.addEventListener('keydown', (event) => {
            if (!activeEnrollmentId) {
                return;
            }

            const target = event.target;
            if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
                return;
            }

            const map = {
                p: 'present',
                a: 'absent',
                t: 'late',
                j: 'justified',
            };

            const status = map[event.key.toLowerCase()];
            if (!status) {
                return;
            }

            event.preventDefault();
            setStatus(activeEnrollmentId, status);
        });
    })();
</script>
@endif
@endsection
