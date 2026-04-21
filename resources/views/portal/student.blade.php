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

<div class="card">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Mis evaluaciones</h2>
        <div class="entity-sub">Informes por curso (solo lectura)</div>
    </div>
    @include('partials.portal-grade-entries', ['gradeEntries' => $portalGradeEntries])
</div>

<div class="card">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Mis recuperativas</h2>
        <div class="entity-sub">Gestiona pago y reserva sin scroll horizontal</div>
    </div>

    @if(filled($makeupPaymentInstructions))
        <div class="makeup-instructions">
            <h3>Instrucciones de pago</h3>
            <p>{!! nl2br(e($makeupPaymentInstructions)) !!}</p>
        </div>
    @endif

    <div class="makeup-student-list">
        @forelse($makeupRequests as $makeupRequest)
            @php($course = $makeupRequest->enrollment?->group?->course)
            <article class="makeup-student-card">
                <div class="makeup-student-head">
                    <div>
                        <div class="table-title">{{ $course?->name ?? 'N/D' }}</div>
                        <div class="table-sub">{{ $makeupRequest->missedSession?->session_date?->format('d/m/Y') ?? 'N/D' }} · {{ ucfirst($makeupRequest->request_type) }}</div>
                    </div>
                    <div>@include('partials.ui.status-badge', ['tone' => in_array($makeupRequest->status, ['approved_for_booking','completed'], true) ? 'ok' : ($makeupRequest->status === 'rejected' ? 'danger' : 'warn'), 'text' => ucfirst(str_replace('_', ' ', $makeupRequest->status))])</div>
                </div>

                <div class="makeup-student-meta">
                    <div><strong>Programa:</strong> {{ $course?->program?->name ?? 'N/D' }}</div>
                    <div><strong>Nivel:</strong> {{ $course?->programLevel?->name ?? 'N/D' }}</div>
                    <div><strong>Monto:</strong> ${{ number_format($makeupRequest->price, 2) }}</div>
                </div>

                <div class="grid-2">
                    <div class="makeup-student-pane">
                        <h4>Pago / Reposo</h4>
                        @if(in_array($makeupRequest->status, ['pending_payment','pending_validation','rejected'], true))
                            <form method="POST" action="{{ route('portal.student.makeups.payment', $makeupRequest) }}" enctype="multipart/form-data" class="stack-xs">
                                @csrf
                                <div>
                                    <label>Comprobante</label>
                                    <input type="file" name="payment_proof" required>
                                </div>
                                <label class="checkbox-inline"><input type="checkbox" name="medical_support_required" value="1"> Con reposo ($5)</label>
                                <div>
                                    <label>Reposo médico (opcional)</label>
                                    <input type="file" name="medical_support">
                                </div>
                                <input name="payment_notes" placeholder="Observación o referencia">
                                <button class="btn secondary" type="submit">Enviar comprobante</button>
                            </form>
                        @else
                            <div class="table-sub">Pago enviado: {{ $makeupRequest->payment_proof ? 'Sí' : 'No' }}</div>
                            <div class="table-sub">Reposo: {{ $makeupRequest->medical_support_attachment ? 'Sí' : 'No' }}</div>
                        @endif
                    </div>

                    <div class="makeup-student-pane">
                        <h4>Reserva de horario</h4>
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
                            <div><strong>Fecha:</strong> {{ $makeupRequest->booking->makeupSession?->session_date?->format('d/m/Y') ?? 'N/D' }}</div>
                            <div class="table-sub">{{ $makeupRequest->booking->makeupSession?->starts_at ?? '' }} - {{ $makeupRequest->booking->makeupSession?->ends_at ?? '' }}</div>
                            <div class="table-sub">{{ $makeupRequest->booking->makeupSession?->teacher?->full_name ?? 'N/D' }}</div>
                        @else
                            <div class="table-sub">Aún no disponible hasta validar el pago.</div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state-inline">No tienes recuperativas pendientes.</div>
        @endforelse
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

<div class="card">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Pagos pendientes de cargos</h2>
        <div class="entity-sub">Toca un cargo y sube el comprobante en el formulario (no hace falta scroll horizontal)</div>
    </div>
    <div class="charge-pending-list">
        @forelse($pendingCharges as $charge)
            <article class="charge-pending-card">
                <div class="charge-pending-head">
                    <div>
                        <div class="table-title">{{ $charge->concept }}</div>
                        <div class="table-sub">Vence: {{ $charge->due_date?->format('d/m/Y') ?? 'N/D' }}</div>
                    </div>
                    <div>
                        @include('partials.ui.status-badge', [
                            'tone' => $charge->status === 'overdue' ? 'danger' : 'warn',
                            'text' => ucfirst($charge->status),
                        ])
                    </div>
                </div>
                <div class="charge-pending-meta">
                    <div><strong>Monto cargo:</strong> ${{ number_format($charge->amount, 2) }}</div>
                </div>
                <div class="charge-pending-actions">
                    <button type="button" class="btn secondary" onclick="document.getElementById('charge-pay-dialog-{{ $charge->id }}').showModal()">Enviar comprobante</button>
                </div>
            </article>
            <dialog id="charge-pay-dialog-{{ $charge->id }}" class="charge-pay-dialog">
                <div class="charge-pay-dialog-head">
                    <h3 class="charge-pay-dialog-title">Enviar comprobante</h3>
                    <button type="button" class="charge-pay-dialog-close" onclick="document.getElementById('charge-pay-dialog-{{ $charge->id }}').close()" aria-label="Cerrar">&times;</button>
                </div>
                <p class="charge-pay-dialog-summary">{{ $charge->concept }} · <strong>${{ number_format($charge->amount, 2) }}</strong></p>
                <form method="POST" action="{{ route('portal.student.charges.payment', $charge) }}" enctype="multipart/form-data" class="stack-xs charge-pay-form">
                    @csrf
                    <div>
                        <label for="charge-amount-{{ $charge->id }}">Monto que pagaste</label>
                        <input id="charge-amount-{{ $charge->id }}" type="number" step="0.01" min="0.01" max="{{ number_format($charge->amount, 2, '.', '') }}" name="amount" placeholder="Ej. {{ number_format($charge->amount, 2, '.', '') }}" required inputmode="decimal">
                    </div>
                    <div>
                        <label for="charge-method-{{ $charge->id }}">Método</label>
                        <input id="charge-method-{{ $charge->id }}" name="payment_method" placeholder="Transferencia, Pago móvil, etc.">
                    </div>
                    <div>
                        <label for="charge-ref-{{ $charge->id }}">Referencia</label>
                        <input id="charge-ref-{{ $charge->id }}" name="reference" placeholder="Número de operación o referencia">
                    </div>
                    <div>
                        <label for="charge-file-{{ $charge->id }}">Archivo del comprobante</label>
                        <input id="charge-file-{{ $charge->id }}" type="file" name="payment_proof" required accept="image/*,.pdf">
                    </div>
                    <div>
                        <label for="charge-notes-{{ $charge->id }}">Observaciones</label>
                        <input id="charge-notes-{{ $charge->id }}" name="notes" placeholder="Opcional">
                    </div>
                    <div class="charge-pay-dialog-footer">
                        <button type="button" class="btn secondary" onclick="document.getElementById('charge-pay-dialog-{{ $charge->id }}').close()">Cancelar</button>
                        <button class="btn" type="submit">Enviar comprobante</button>
                    </div>
                </form>
            </dialog>
        @empty
            <div class="empty-state-inline">No tienes cargos pendientes por pagar.</div>
        @endforelse
    </div>
    @if($chargePaymentRequests->isNotEmpty())
        <div class="charge-request-list">
            <h3 class="section-title section-title-sm charge-request-heading">Solicitudes enviadas</h3>
            @foreach($chargePaymentRequests as $paymentRequest)
                <article class="charge-request-card">
                    <div class="charge-request-line">
                        <span class="table-title">{{ $paymentRequest->charge?->concept ?? 'N/D' }}</span>
                        <span>@include('partials.ui.status-badge', [
                            'tone' => $paymentRequest->status === \App\Models\ChargePaymentRequest::STATUS_APPROVED ? 'ok' : ($paymentRequest->status === \App\Models\ChargePaymentRequest::STATUS_REJECTED ? 'danger' : 'warn'),
                            'text' => ucfirst(str_replace('_', ' ', $paymentRequest->status)),
                        ])</span>
                    </div>
                    <div class="charge-request-meta">
                        <span>{{ $paymentRequest->submitted_at?->format('d/m/Y H:i') ?? 'N/D' }}</span>
                        <span>${{ number_format($paymentRequest->amount, 2) }}</span>
                    </div>
                    @if($paymentRequest->rejection_reason)
                        <div class="charge-request-reject"><strong>Motivo:</strong> {{ $paymentRequest->rejection_reason }}</div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>

<div class="grid-2">
    <div class="card table-card"><h3 class="section-title section-title-sm">Cargos</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Concepto</th><th>Monto</th><th>Estado</th></tr></thead><tbody>@forelse($charges as $charge)<tr><td>{{ $charge->concept }}</td><td>${{ number_format($charge->amount,2) }}</td><td>{{ $charge->status }}</td></tr>@empty<tr><td colspan="3"><div class="empty-state-inline">Sin cargos</div></td></tr>@endforelse</tbody></table></div></div>
    <div class="card table-card"><h3 class="section-title section-title-sm">Pagos</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>Monto</th><th>Método</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ $payment->paid_at?->format('Y-m-d') }}</td><td>${{ number_format($payment->amount,2) }}</td><td>{{ $payment->method }}</td></tr>@empty<tr><td colspan="3"><div class="empty-state-inline">Sin pagos</div></td></tr>@endforelse</tbody></table></div></div>
</div>
@push('scripts')
<script>
document.querySelectorAll('dialog.charge-pay-dialog').forEach(function (dlg) {
    dlg.addEventListener('click', function (e) {
        if (e.target === dlg) {
            dlg.close();
        }
    });
});
</script>
@endpush
@endsection
