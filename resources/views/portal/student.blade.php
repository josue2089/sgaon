@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Portal del Alumno</h1>
        <p class="page-subtitle"><strong>{{ $student->full_name }}</strong> · {{ $student->email ?: 'Sin email' }}</p>
    </div>
</div>

<div class="summary-grid student-summary-grid">
    <div class="card summary-card">
        <div class="summary-label">Inscripciones</div>
        <div class="summary-value">{{ $enrollments->count() }}</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Recuperativas abiertas</div>
        <div class="summary-value">{{ $makeupRequests->whereIn('status', ['pending_payment','pending_validation','approved_for_booking','booked'])->count() }}</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Cargos</div>
        <div class="summary-value">${{ number_format($charges->sum('amount'), 2) }}</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Pagos</div>
        <div class="summary-value">${{ number_format($payments->sum('amount'), 2) }}</div>
    </div>
</div>

<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title section-title-md">Agenda</h2>
        <div class="entity-sub">Clases regulares y recuperativas reservadas</div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Tipo</th>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Clase</th>
                <th>Profesor</th>
            </tr>
            </thead>
            <tbody>
            @forelse($agenda as $item)
                <tr>
                    <td>@include('partials.ui.status-badge', ['tone' => $item['kind'] === 'makeup' ? 'warn' : 'info', 'text' => $item['kind'] === 'makeup' ? 'Recuperativa' : 'Regular'])</td>
                    <td>{{ $item['date']?->format('d/m/Y') ?? 'N/D' }}</td>
                    <td>{{ $item['starts_at'] ?? 'N/D' }} - {{ $item['ends_at'] ?? 'N/D' }}</td>
                    <td>{{ $item['label'] }}</td>
                    <td>{{ $item['teacher'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-inline">No hay agenda próxima.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title section-title-md">Mis recuperativas</h2>
        <div class="entity-sub">Carga tu comprobante y reserva horario cuando sea aprobado</div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Clase perdida</th>
                <th>Programa</th>
                <th>Costo</th>
                <th>Estado</th>
                <th>Pago / Reposo</th>
                <th>Reserva</th>
            </tr>
            </thead>
            <tbody>
            @forelse($makeupRequests as $makeupRequest)
                @php($course = $makeupRequest->enrollment?->group?->course)
                <tr>
                    <td>
                        <div class="table-title">{{ $course?->name ?? 'N/D' }}</div>
                        <div class="table-sub">{{ $makeupRequest->missedSession?->session_date?->format('d/m/Y') ?? 'N/D' }} · {{ ucfirst($makeupRequest->request_type) }}</div>
                    </td>
                    <td>
                        <div>{{ $course?->program?->name ?? 'N/D' }}</div>
                        <div class="table-sub">{{ $course?->programLevel?->name ?? 'N/D' }}</div>
                    </td>
                    <td>${{ number_format($makeupRequest->price, 2) }}</td>
                    <td>@include('partials.ui.status-badge', ['tone' => in_array($makeupRequest->status, ['approved_for_booking','completed'], true) ? 'ok' : ($makeupRequest->status === 'rejected' ? 'danger' : 'warn'), 'text' => ucfirst(str_replace('_', ' ', $makeupRequest->status))])</td>
                    <td>
                        @if(in_array($makeupRequest->status, ['pending_payment','pending_validation','rejected'], true))
                            <form method="POST" action="{{ route('portal.student.makeups.payment', $makeupRequest) }}" enctype="multipart/form-data" class="stack-xs">
                                @csrf
                                <input type="file" name="payment_proof" required>
                                <label class="checkbox-inline"><input type="checkbox" name="medical_support_required" value="1"> Con reposo ($5)</label>
                                <input type="file" name="medical_support">
                                <input name="payment_notes" placeholder="Observación o referencia">
                                <button class="btn secondary" type="submit">Enviar comprobante</button>
                            </form>
                        @else
                            <div class="table-sub">Pago enviado: {{ $makeupRequest->payment_proof ? 'Sí' : 'No' }}</div>
                            <div class="table-sub">Reposo: {{ $makeupRequest->medical_support_attachment ? 'Sí' : 'No' }}</div>
                        @endif
                    </td>
                    <td>
                        @if($makeupRequest->status === 'approved_for_booking')
                            <form method="POST" action="{{ route('portal.student.makeups.book', $makeupRequest) }}" class="stack-xs">
                                @csrf
                                <select name="makeup_session_id" required>
                                    <option value="">Seleccione horario</option>
                                    @foreach($eligibleMakeupSessions as $session)
                                        @if((int) $session->program_level_id === (int) $course?->program_level_id)
                                            <option value="{{ $session->id }}">{{ $session->display_label }} · {{ $session->teacher?->full_name ?? 'N/D' }} · Cupos {{ $session->available_slots }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button class="btn secondary" type="submit">Reservar</button>
                            </form>
                        @elseif($makeupRequest->booking)
                            <div>{{ $makeupRequest->booking->makeupSession?->session_date?->format('d/m/Y') ?? 'N/D' }}</div>
                            <div class="table-sub">{{ $makeupRequest->booking->makeupSession?->starts_at ?? '' }} - {{ $makeupRequest->booking->makeupSession?->ends_at ?? '' }}</div>
                            <div class="table-sub">{{ $makeupRequest->booking->makeupSession?->teacher?->full_name ?? 'N/D' }}</div>
                        @else
                            <div class="table-sub">Aún no disponible</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state-inline">No tienes recuperativas pendientes.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card table-card">
    <h3 class="section-title section-title-sm">Inscripciones</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Curso</th><th>Grupo</th><th>Estado</th><th>Progreso</th></tr></thead>
            <tbody>
            @forelse($enrollments as $enrollment)
                <tr>
                    <td>{{ $enrollment->group->course->name ?? '' }}</td>
                    <td>{{ $enrollment->group->name ?? '' }}</td>
                    <td><span class="status-pill {{ $enrollment->status === 'active' ? 'success' : ($enrollment->status === 'completed' ? 'info' : 'warn') }}">{{ $enrollment->status }}</span></td>
                    <td>{{ $enrollment->progress }}%</td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty-state-inline">Sin inscripciones</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card table-card">
    <h3 class="section-title section-title-sm">Resumen de Asistencia</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Grupo</th><th>Presente</th><th>Ausente</th><th>Tarde</th><th>Justificada</th></tr></thead>
            <tbody>
            @forelse($attendance as $item)
                <tr><td>{{ $item->group->name ?? '' }}</td><td>{{ $item->present_count }}</td><td>{{ $item->absent_count }}</td><td>{{ $item->late_count }}</td><td>{{ $item->justified_count }}</td></tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-inline">Sin registros</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    <div class="card table-card"><h3 class="section-title section-title-sm">Cargos</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Concepto</th><th>Monto</th><th>Estado</th></tr></thead><tbody>@forelse($charges as $charge)<tr><td>{{ $charge->concept }}</td><td>${{ number_format($charge->amount,2) }}</td><td>{{ $charge->status }}</td></tr>@empty<tr><td colspan="3"><div class="empty-state-inline">Sin cargos</div></td></tr>@endforelse</tbody></table></div></div>
    <div class="card table-card"><h3 class="section-title section-title-sm">Pagos</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>Monto</th><th>Método</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ $payment->paid_at?->format('Y-m-d') }}</td><td>${{ number_format($payment->amount,2) }}</td><td>{{ $payment->method }}</td></tr>@empty<tr><td colspan="3"><div class="empty-state-inline">Sin pagos</div></td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
