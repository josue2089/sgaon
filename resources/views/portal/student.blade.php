@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Portal del Alumno</h1>
        <p class="page-subtitle"><strong>{{ $student->full_name }}</strong> · {{ $student->email ?: 'Sin email' }}</p>
    </div>
</div>

@php
    $tabCounts = [
        'inscripciones' => $enrollments->count(),
        'evaluaciones' => $portalGradeEntries->count(),
        'agenda' => $agenda->count(),
        'pagos' => $pendingCharges->count(),
        'recuperativas' => $makeupRequests->whereIn('status', ['pending_payment','pending_validation','approved_for_booking','booked'])->count(),
    ];
@endphp

<div class="summary-grid student-summary-grid">
    <div class="card summary-card"><div class="summary-label">Inscripciones</div><div class="summary-value">{{ $enrollments->count() }}</div></div>
    <div class="card summary-card"><div class="summary-label">Recuperativas abiertas</div><div class="summary-value">{{ $makeupRequests->whereIn('status', ['pending_payment','pending_validation','approved_for_booking','booked'])->count() }}</div></div>
    <div class="card summary-card"><div class="summary-label">Cargos</div><div class="summary-value">${{ number_format($charges->sum('amount'), 2) }}</div></div>
    <div class="card summary-card"><div class="summary-label">Pagos</div><div class="summary-value">${{ number_format($payments->sum('amount'), 2) }}</div></div>
</div>

<div class="portal-tabs" role="tablist" aria-label="Secciones del portal del alumno">
    <button type="button" class="portal-tab is-active" data-tab-target="resumen" role="tab" aria-selected="true">Resumen</button>
    <button type="button" class="portal-tab" data-tab-target="inscripciones" role="tab" aria-selected="false">Inscripciones <span class="portal-tab-badge">{{ $tabCounts['inscripciones'] }}</span></button>
    <button type="button" class="portal-tab" data-tab-target="evaluaciones" role="tab" aria-selected="false">Evaluaciones <span class="portal-tab-badge">{{ $tabCounts['evaluaciones'] }}</span></button>
    <button type="button" class="portal-tab" data-tab-target="agenda" role="tab" aria-selected="false">Agenda <span class="portal-tab-badge">{{ $tabCounts['agenda'] }}</span></button>
    <button type="button" class="portal-tab" data-tab-target="pagos" role="tab" aria-selected="false">Pagos <span class="portal-tab-badge">{{ $tabCounts['pagos'] }}</span></button>
    <button type="button" class="portal-tab" data-tab-target="recuperativas" role="tab" aria-selected="false">Recuperativas <span class="portal-tab-badge">{{ $tabCounts['recuperativas'] }}</span></button>
</div>

<section class="portal-tab-panel is-active" data-tab-panel="resumen">
    <div class="card" id="renewal-enrollment">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Inscripción al siguiente curso</h2>
            <div class="entity-sub">Cursos habilitados desde recordatorio de renovación</div>
        </div>
        <div class="makeup-student-list">
            @forelse($renewalOffers as $offer)
                <article class="makeup-student-card">
                    <div class="makeup-student-head">
                        <div>
                            <div class="table-title">{{ $offer['target_course']->name }}</div>
                            <div class="table-sub">Desde {{ $offer['source_course']->name }}</div>
                        </div>
                        <div>
                            @if($offer['already_enrolled'])
                                @include('partials.ui.status-badge', ['tone' => 'ok', 'text' => 'Inscrito'])
                            @elseif($offer['eligible'])
                                @include('partials.ui.status-badge', ['tone' => 'info', 'text' => 'Disponible'])
                            @else
                                @include('partials.ui.status-badge', ['tone' => 'danger', 'text' => 'No elegible'])
                            @endif
                        </div>
                    </div>
                    <div class="makeup-student-meta">
                        <div><strong>Inicio:</strong> {{ $offer['target_course']->start_date?->format('d/m/Y') ?? 'N/D' }}</div>
                        <div><strong>Horario:</strong> {{ $offer['target_course']->scheduleTemplate?->display_label ?? 'N/D' }}</div>
                    </div>
                    @if(! $offer['already_enrolled'] && $offer['eligible'])
                        <form method="POST" action="{{ route('portal.student.renewals.enroll', $offer['target_course']) }}">
                            @csrf
                            <button class="btn secondary" type="submit">Inscribirme en este curso</button>
                        </form>
                    @elseif(! $offer['eligible'])
                        <div class="table-sub">Tu evaluación final está en revisión (Need Support).</div>
                    @endif
                </article>
            @empty
                <div class="empty-state-inline">Aún no hay cursos de renovación habilitados.</div>
            @endforelse
        </div>
    </div>
</section>

<section class="portal-tab-panel" data-tab-panel="agenda">
    @if($agenda->isEmpty() && $attendance->isEmpty())
        <div class="card">
            <div class="empty-state-inline">No tienes clases próximas ni registros de asistencia todavía.</div>
        </div>
    @else
    <div class="card table-card">
        <div class="section-head"><h2 class="section-title section-title-md">Agenda</h2><div class="entity-sub">Clases regulares y recuperativas reservadas</div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tipo</th><th>Fecha</th><th>Horario</th><th>Clase</th><th>Profesor</th></tr></thead>
                <tbody>
                @forelse($agenda as $item)
                    <tr><td>@include('partials.ui.status-badge', ['tone' => $item['kind'] === 'makeup' ? 'warn' : 'info', 'text' => $item['kind'] === 'makeup' ? 'Recuperativa' : 'Regular'])</td><td>{{ $item['date']?->format('d/m/Y') ?? 'N/D' }}</td><td>{{ $item['starts_at'] ?? 'N/D' }} - {{ $item['ends_at'] ?? 'N/D' }}</td><td>{{ $item['label'] }}</td><td>{{ $item['teacher'] }}</td></tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state-inline">No hay agenda próxima.</div></td></tr>
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
    @endif
</section>

<section class="portal-tab-panel" data-tab-panel="evaluaciones">
    <div class="card">
        <div class="section-head section-head-tight"><h2 class="section-title section-title-md">Mis evaluaciones</h2><div class="entity-sub">Informes por curso (solo lectura)</div></div>
        @if($portalGradeEntries->isEmpty())
            <div class="empty-state-inline">Aún no hay evaluaciones publicadas para tu perfil.</div>
        @else
            @include('partials.portal-grade-entries', ['gradeEntries' => $portalGradeEntries])
        @endif
    </div>
</section>

<section class="portal-tab-panel" data-tab-panel="inscripciones">
    @if($enrollments->isEmpty())
        <div class="card">
            <div class="empty-state-inline">Aún no tienes inscripciones activas o históricas.</div>
        </div>
    @else
        <div class="card table-card">
            <h3 class="section-title section-title-sm">Inscripciones</h3>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Curso</th><th>Grupo</th><th>Estado</th><th>Progreso</th></tr></thead><tbody>
            @foreach($enrollments as $enrollment)
                <tr><td>{{ $enrollment->group->course->name ?? '' }}</td><td>{{ $enrollment->group->name ?? '' }}</td><td><span class="status-pill {{ $enrollment->status === 'active' ? 'success' : ($enrollment->status === 'completed' ? 'info' : 'warn') }}">{{ $enrollment->status }}</span></td><td>{{ $enrollment->progress }}%</td></tr>
            @endforeach
            </tbody></table></div>
        </div>
    @endif
</section>

<section class="portal-tab-panel" data-tab-panel="recuperativas">
@if($makeupRequests->isEmpty())
<div class="card">
    <div class="empty-state-inline">No tienes recuperativas pendientes por gestionar.</div>
</div>
@else
<div class="card">
    <div class="section-head section-head-tight"><h2 class="section-title section-title-md">Mis recuperativas</h2><div class="entity-sub">Gestiona pago y reserva sin scroll horizontal</div></div>
    @if(filled($makeupPaymentInstructions))
        <div class="makeup-instructions"><h3>Instrucciones de pago</h3><p>{!! nl2br(e($makeupPaymentInstructions)) !!}</p></div>
    @endif
    <div class="makeup-student-list">
        @forelse($makeupRequests as $makeupRequest)
            @php($course = $makeupRequest->enrollment?->group?->course)
            <article class="makeup-student-card">
                <div class="makeup-student-head"><div><div class="table-title">{{ $course?->name ?? 'N/D' }}</div><div class="table-sub">{{ $makeupRequest->missedSession?->session_date?->format('d/m/Y') ?? 'N/D' }} · {{ ucfirst($makeupRequest->request_type) }}</div></div><div>@include('partials.ui.status-badge', ['tone' => in_array($makeupRequest->status, ['approved_for_booking','completed'], true) ? 'ok' : ($makeupRequest->status === 'rejected' ? 'danger' : 'warn'), 'text' => ucfirst(str_replace('_', ' ', $makeupRequest->status))])</div></div>
                <div class="makeup-student-meta"><div><strong>Programa:</strong> {{ $course?->program?->name ?? 'N/D' }}</div><div><strong>Nivel:</strong> {{ $course?->programLevel?->name ?? 'N/D' }}</div><div><strong>Monto:</strong> ${{ number_format($makeupRequest->price, 2) }}</div></div>
                <div class="grid-2">
                    <div class="makeup-student-pane">
                        <h4>Pago / Reposo</h4>
                        @if(in_array($makeupRequest->status, ['pending_payment','pending_validation','rejected'], true))
                            <form method="POST" action="{{ route('portal.student.makeups.payment', $makeupRequest) }}" enctype="multipart/form-data" class="stack-xs">@csrf<div><label>Comprobante</label><input type="file" name="payment_proof" required></div><label class="checkbox-inline"><input type="checkbox" name="medical_support_required" value="1"> Con reposo ($5)</label><div><label>Reposo médico (opcional)</label><input type="file" name="medical_support"></div><input name="payment_notes" placeholder="Observación o referencia"><button class="btn secondary" type="submit">Enviar comprobante</button></form>
                        @else
                            <div class="table-sub">Pago enviado: {{ $makeupRequest->payment_proof ? 'Sí' : 'No' }}</div><div class="table-sub">Reposo: {{ $makeupRequest->medical_support_attachment ? 'Sí' : 'No' }}</div>
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
                            <div><strong>Fecha:</strong> {{ $makeupRequest->booking->makeupSession?->session_date?->format('d/m/Y') ?? 'N/D' }}</div><div class="table-sub">{{ $makeupRequest->booking->makeupSession?->starts_at ?? '' }} - {{ $makeupRequest->booking->makeupSession?->ends_at ?? '' }}</div><div class="table-sub">{{ $makeupRequest->booking->makeupSession?->teacher?->full_name ?? 'N/D' }}</div>
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
@endif
</section>

<section class="portal-tab-panel" data-tab-panel="pagos">
@if($pendingCharges->isEmpty() && $chargePaymentRequests->isEmpty() && $charges->isEmpty() && $payments->isEmpty())
    <div class="card">
        <div class="empty-state-inline">No tienes movimientos de pagos ni cargos registrados.</div>
    </div>
@else
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
                    @php($chargeBalance = \App\Support\FinanceReconcile::outstandingForCharge($charge))
                    <div><strong>Saldo pendiente:</strong> {{ \App\Support\MoneyFormat::chargeAmount($charge, $charge->isEur() ? ($bcvEurRate['rate'] ?? 0) : ($bcvRate['rate'] ?? 0)) }}</div>
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
                <p class="charge-pay-dialog-summary">{{ $charge->concept }} · <strong>{{ \App\Support\MoneyFormat::chargeAmount($charge, $charge->isEur() ? ($bcvEurRate['rate'] ?? 0) : ($bcvRate['rate'] ?? 0)) }}</strong> pendiente</p>
                <form method="POST" action="{{ route('portal.student.charges.payment', $charge) }}" enctype="multipart/form-data" class="stack-xs charge-pay-form">
                    @csrf
                    @include('partials.payment-currency-fields', [
                        'prefix' => 'charge-'.$charge->id,
                        'chargeCurrency' => $charge->currencyCode(),
                        'balanceAmount' => $chargeBalance,
                        'usdExchangeRate' => $bcvRate['rate'] ?? 0,
                        'eurExchangeRate' => $bcvEurRate['rate'] ?? 0,
                        'paymentMethods' => $paymentMethods,
                    ])
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
                        <span>
                            @if(($paymentRequest->currency ?? 'USD') === 'VES')
                                {{ \App\Support\MoneyFormat::ves((float) ($paymentRequest->original_amount ?? $paymentRequest->amount)) }}
                                · {{ \App\Support\MoneyFormat::usd((float) $paymentRequest->amount) }}
                            @else
                                {{ \App\Support\MoneyFormat::usd((float) $paymentRequest->amount) }}
                            @endif
                        </span>
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
    <div class="card table-card"><h3 class="section-title section-title-sm">Cargos</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Concepto</th><th>Monto</th><th>Estado</th></tr></thead><tbody>@forelse($charges as $charge)<tr><td>{{ $charge->concept }}</td><td>{{ \App\Support\MoneyFormat::chargeAmount($charge, $charge->isEur() ? ($bcvEurRate['rate'] ?? 0) : ($bcvRate['rate'] ?? 0)) }}</td><td>{{ $charge->status }}</td></tr>@empty<tr><td colspan="3"><div class="empty-state-inline">Sin cargos</div></td></tr>@endforelse</tbody></table></div></div>
    <div class="card table-card"><h3 class="section-title section-title-sm">Pagos</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>Monto</th><th>Método</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ $payment->paid_at?->format('Y-m-d') }}</td><td>{{ \App\Support\MoneyFormat::dualLine($payment) }}</td><td>{{ $payment->method }}</td></tr>@empty<tr><td colspan="3"><div class="empty-state-inline">Sin pagos</div></td></tr>@endforelse</tbody></table></div></div>
    </div>
@endif
</section>
@push('scripts')
<script>
document.querySelectorAll('dialog.charge-pay-dialog').forEach(function (dlg) {
    dlg.addEventListener('click', function (e) {
        if (e.target === dlg) {
            dlg.close();
        }
    });
});

(function () {
    const tabs = Array.from(document.querySelectorAll('.portal-tab'));
    const panels = Array.from(document.querySelectorAll('.portal-tab-panel'));
    if (!tabs.length || !panels.length) return;
    function activate(target, updateHash = true) {
        tabs.forEach((tab) => {
            const active = tab.dataset.tabTarget === target;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.tabPanel === target));
        if (updateHash) window.history.replaceState(null, '', '#tab-' + target);
    }
    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.tabTarget)));
    const hashTab = window.location.hash.replace('#tab-', '');
    if (hashTab && tabs.some((tab) => tab.dataset.tabTarget === hashTab)) activate(hashTab, false);
    if (window.location.hash === '#renewal-enrollment') {
        activate('resumen', false);
        const block = document.getElementById('renewal-enrollment');
        if (block) block.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
})();
</script>
@endpush
@endsection
