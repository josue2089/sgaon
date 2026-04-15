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
            @foreach(['pending_payment' => 'Pendiente pago','pending_validation' => 'Pendiente validación','approved_for_booking' => 'Aprobada para reservar','booked' => 'Reservada','completed' => 'Completada','missed' => 'No atendida','rejected' => 'Rechazada'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="student_id">
            <option value="">Todos los alumnos</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected((int) ($filters['student_id'] ?? 0) === (int) $student->id)>{{ $student->full_name }}</option>
            @endforeach
        </select>
        <select name="program_id" id="makeup-program-filter">
            <option value="">Todos los programas</option>
            @foreach($programs as $program)
                <option value="{{ $program->id }}" @selected((int) ($filters['program_id'] ?? 0) === (int) $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
        <select name="program_level_id">
            <option value="">Todos los niveles</option>
            @foreach($programLevels as $level)
                <option value="{{ $level->id }}" @selected((int) ($filters['program_level_id'] ?? 0) === (int) $level->id)>{{ $level->name }}</option>
            @endforeach
        </select>
        <input type="date" name="session_date" value="{{ $filters['session_date'] ?? '' }}">
        <button class="btn secondary" type="submit">Filtrar</button>
    </div>
</form>

<div class="grid-2">
    <div class="card">
        <h3 class="section-title section-title-sm">Crear bloque recuperativo</h3>
        <form method="POST" action="{{ route('makeups.sessions.store') }}" class="stack-sm">
            @csrf
            <div>
                <label>Profesor</label>
                <select name="teacher_id" required>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Programa</label>
                <select name="program_id" required>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}">{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Nivel</label>
                <select name="program_level_id" required>
                    @foreach($programLevels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Horario base</label>
                <select name="schedule_template_id">
                    <option value="">Sin horario base</option>
                    @foreach($scheduleTemplates as $schedule)
                        <option value="{{ $schedule->id }}">{{ $schedule->display_label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid-3">
                <div><label>Fecha</label><input type="date" name="session_date" required></div>
                <div><label>Inicio</label><input type="time" name="starts_at" required></div>
                <div><label>Fin</label><input type="time" name="ends_at" required></div>
            </div>
            <div class="grid-2">
                <div><label>Cupo</label><input type="number" min="1" max="50" name="capacity" value="8" required></div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="open">open</option>
                        <option value="full">full</option>
                        <option value="cancelled">cancelled</option>
                        <option value="completed">completed</option>
                    </select>
                </div>
            </div>
            <div><label>Notas</label><textarea name="notes"></textarea></div>
            <button class="btn" type="submit">Crear bloque</button>
        </form>
    </div>

    <div class="card table-card">
        <h3 class="section-title section-title-sm">Calendario recuperativo</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Horario</th>
                    <th>Profesor</th>
                    <th>Programa</th>
                    <th>Nivel</th>
                    <th>Cupos</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse($makeupSessions as $session)
                    <tr>
                        <td>{{ $session->session_date?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td>{{ $session->starts_at }} - {{ $session->ends_at }}</td>
                        <td>{{ $session->teacher?->full_name ?? 'N/D' }}</td>
                        <td>{{ $session->program?->name ?? 'N/D' }}</td>
                        <td>{{ $session->programLevel?->name ?? 'N/D' }}</td>
                        <td>{{ $session->booked_count }}/{{ $session->capacity }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $session->status === 'open' ? 'ok' : ($session->status === 'full' ? 'warn' : 'info'), 'text' => ucfirst($session->status)])</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state-inline">No hay bloques recuperativos.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card table-card">
    <h3 class="section-title section-title-sm">Solicitudes</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Alumno</th>
                <th>Clase perdida</th>
                <th>Programa</th>
                <th>Costo</th>
                <th>Evidencias</th>
                <th>Estado</th>
                <th>Reserva</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            @forelse($requests as $makeupRequest)
                @php($course = $makeupRequest->enrollment?->group?->course)
                <tr>
                    <td>
                        <div class="table-title">{{ $makeupRequest->student?->full_name ?? 'N/D' }}</div>
                        <div class="table-sub">{{ $makeupRequest->student?->email ?? 'Sin email' }}</div>
                    </td>
                    <td>
                        <div>{{ $course?->name ?? 'N/D' }}</div>
                        <div class="table-sub">{{ $makeupRequest->missedSession?->session_date?->format('d/m/Y') ?? 'N/D' }}</div>
                    </td>
                    <td>
                        <div>{{ $course?->program?->name ?? 'N/D' }}</div>
                        <div class="table-sub">{{ $course?->programLevel?->name ?? 'N/D' }}</div>
                    </td>
                    <td>${{ number_format($makeupRequest->price, 2) }}</td>
                    <td>
                        <div class="table-sub">
                            Pago:
                            @if($makeupRequest->payment_proof)
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($makeupRequest->payment_proof->file_path) }}" target="_blank">Ver archivo</a>
                            @else
                                No
                            @endif
                        </div>
                        <div class="table-sub">
                            Reposo:
                            @if($makeupRequest->medical_support_attachment)
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($makeupRequest->medical_support_attachment->file_path) }}" target="_blank">Ver archivo</a>
                            @else
                                No
                            @endif
                        </div>
                    </td>
                    <td>@include('partials.ui.status-badge', ['tone' => in_array($makeupRequest->status, ['completed','approved_for_booking'], true) ? 'ok' : ($makeupRequest->status === 'rejected' ? 'danger' : 'warn'), 'text' => ucfirst(str_replace('_', ' ', $makeupRequest->status))])</td>
                    <td>
                        @if($makeupRequest->booking)
                            <div>{{ $makeupRequest->booking->makeupSession?->session_date?->format('d/m/Y') ?? 'N/D' }}</div>
                            <div class="table-sub">{{ $makeupRequest->booking->makeupSession?->starts_at ?? '' }} - {{ $makeupRequest->booking->makeupSession?->ends_at ?? '' }}</div>
                            <form method="POST" action="{{ route('makeups.bookings.update', $makeupRequest->booking) }}" class="stack-xs">
                                @csrf
                                @method('PATCH')
                                <select name="status">
                                    @foreach(['reserved','attended','missed','cancelled'] as $status)
                                        <option value="{{ $status }}" @selected($makeupRequest->booking->status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                <button class="btn secondary" type="submit">Actualizar</button>
                            </form>
                        @else
                            <span class="table-sub">Sin reserva</span>
                        @endif
                    </td>
                    <td>
                        @if($makeupRequest->status === 'pending_validation')
                            <form method="POST" action="{{ route('makeups.requests.review', $makeupRequest) }}" class="stack-xs">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="approve">
                                <input type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}">
                                <input name="method" placeholder="Método" value="Transferencia">
                                <input name="reference" placeholder="Referencia">
                                <button class="btn secondary" type="submit">Aprobar</button>
                            </form>
                            <form method="POST" action="{{ route('makeups.requests.review', $makeupRequest) }}" class="stack-xs" style="margin-top:.5rem;">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="reject">
                                <input name="rejection_reason" placeholder="Motivo rechazo">
                                <button class="btn secondary" type="submit">Rechazar</button>
                            </form>
                        @elseif($makeupRequest->payment?->receipt)
                            <a href="{{ route('finance.receipts.show', $makeupRequest->payment->receipt) }}">Ver recibo</a>
                        @else
                            <a href="{{ route('students.show', $makeupRequest->student_id) }}">Ver alumno</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state-inline">No hay solicitudes recuperativas.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())
        {{ $requests->links() }}
    @endif
</div>
@endsection
