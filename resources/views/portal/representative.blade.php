@extends('layouts.app')
@section('content')
<div class="card module-head">
    <div>
        <h2>Portal del Representante</h2>
        <p><strong>{{ $representative->first_name }} {{ $representative->last_name }}</strong> | {{ $representative->email }}</p>
    </div>
</div>

@forelse($students as $student)
    @php
        $gradeEntries = ($repGradeEntriesByStudentId[(int) $student->id] ?? collect())->sortByDesc(fn ($e) => optional($e->evaluationSet)->evaluated_on);
        $pendingCharges = $student->charges->whereIn('status', ['pending', 'partial', 'overdue'])->values();
        $tabCounts = [
            'inscripciones' => $student->enrollments->count(),
            'evaluaciones' => $gradeEntries->count(),
            'pagos' => $pendingCharges->count(),
        ];
    @endphp

    <div class="card rep-student-card">
        <div class="section-head section-head-tight">
            <div>
                <h3 class="section-title section-title-md">{{ $student->full_name }}</h3>
                <div class="entity-sub">Estado: @include('partials.ui.status-badge', ['tone' => $student->status === 'active' ? 'ok' : 'warn', 'text' => $student->status])</div>
            </div>
        </div>

        <div class="summary-grid student-summary-grid">
            <div class="card summary-card">
                <div class="summary-label">Inscripciones</div>
                <div class="summary-value">{{ $tabCounts['inscripciones'] }}</div>
            </div>
            <div class="card summary-card">
                <div class="summary-label">Evaluaciones</div>
                <div class="summary-value">{{ $tabCounts['evaluaciones'] }}</div>
            </div>
            <div class="card summary-card">
                <div class="summary-label">Cargos pendientes</div>
                <div class="summary-value">{{ $tabCounts['pagos'] }}</div>
            </div>
            <div class="card summary-card">
                <div class="summary-label">Saldo</div>
                <div class="summary-value">${{ number_format($student->charges->sum('amount') - $student->payments->sum('amount'), 2) }}</div>
            </div>
        </div>

        <div class="portal-tabs rep-portal-tabs" role="tablist" aria-label="Secciones de {{ $student->full_name }}">
            <button type="button" class="portal-tab is-active" data-tab-target="resumen" role="tab" aria-selected="true">Resumen</button>
            <button type="button" class="portal-tab" data-tab-target="inscripciones" role="tab" aria-selected="false">Inscripciones <span class="portal-tab-badge">{{ $tabCounts['inscripciones'] }}</span></button>
            <button type="button" class="portal-tab" data-tab-target="evaluaciones" role="tab" aria-selected="false">Evaluaciones <span class="portal-tab-badge">{{ $tabCounts['evaluaciones'] }}</span></button>
            <button type="button" class="portal-tab" data-tab-target="pagos" role="tab" aria-selected="false">Pagos <span class="portal-tab-badge">{{ $tabCounts['pagos'] }}</span></button>
        </div>

        <section class="portal-tab-panel is-active" data-tab-panel="resumen">
            <div class="grid-2">
                <div class="card table-card">
                    <h4 class="section-title section-title-sm">Resumen financiero</h4>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Cargos</th><th>Pagos</th><th>Saldo</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td>${{ number_format($student->charges->sum('amount'), 2) }}</td>
                                    <td>${{ number_format($student->payments->sum('amount'), 2) }}</td>
                                    <td>${{ number_format($student->charges->sum('amount') - $student->payments->sum('amount'), 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card table-card">
                    <h4 class="section-title section-title-sm">Última actividad</h4>
                    @if($student->chargePaymentRequests->isNotEmpty())
                        @php($latestRequest = $student->chargePaymentRequests->sortByDesc('submitted_at')->first())
                        <div class="table-sub">Último comprobante: {{ $latestRequest->submitted_at?->format('d/m/Y H:i') ?? 'N/D' }}</div>
                        <div class="table-sub">Estado: {{ ucfirst(str_replace('_', ' ', $latestRequest->status)) }}</div>
                    @else
                        <div class="empty-state-inline">No hay actividad de pagos registrada.</div>
                    @endif
                </div>
            </div>
        </section>

        <section class="portal-tab-panel" data-tab-panel="inscripciones">
            @if($student->enrollments->isEmpty())
                <div class="card"><div class="empty-state-inline">Este alumno no tiene inscripciones registradas.</div></div>
            @else
                <div class="card table-card">
                    <h4 class="section-title section-title-sm">Inscripciones</h4>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Curso</th><th>Grupo</th><th>Estado</th></tr></thead>
                            <tbody>
                                @foreach($student->enrollments as $enrollment)
                                    <tr>
                                        <td>{{ $enrollment->group->course->name ?? '' }}</td>
                                        <td>{{ $enrollment->group->name ?? '' }}</td>
                                        <td>{{ ucfirst($enrollment->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        <section class="portal-tab-panel" data-tab-panel="evaluaciones">
            <div class="card">
                <h4 class="section-title section-title-sm">Evaluaciones</h4>
                @if($gradeEntries->isEmpty())
                    <div class="empty-state-inline">Aún no hay evaluaciones publicadas para este alumno.</div>
                @else
                    @include('partials.portal-grade-entries', ['gradeEntries' => $gradeEntries])
                @endif
            </div>
        </section>

        <section class="portal-tab-panel" data-tab-panel="pagos">
            @if($pendingCharges->isEmpty() && $student->chargePaymentRequests->isEmpty())
                <div class="card"><div class="empty-state-inline">No hay cargos pendientes ni solicitudes de pago para este alumno.</div></div>
            @else
                <div class="card">
                    <h4 class="section-title section-title-sm">Pagos pendientes</h4>
                    @if($pendingCharges->isEmpty())
                        <div class="empty-state-inline">No hay cargos pendientes por pagar.</div>
                    @else
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>Cargo</th><th>Monto</th><th>Estado</th><th>Comprobante</th></tr></thead>
                                <tbody>
                                @foreach($pendingCharges as $charge)
                                    <tr>
                                        <td>{{ $charge->concept }}</td>
                                        <td>${{ number_format($charge->amount, 2) }}</td>
                                        <td>{{ ucfirst($charge->status) }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('portal.representative.charges.payment', $charge) }}" enctype="multipart/form-data" class="stack-xs">
                                                @csrf
                                                <input type="number" step="0.01" min="0.01" max="{{ number_format($charge->amount, 2, '.', '') }}" name="amount" placeholder="Monto" required>
                                                <input name="payment_method" placeholder="Método">
                                                <input name="reference" placeholder="Referencia">
                                                <input type="file" name="payment_proof" required>
                                                <input name="notes" placeholder="Observaciones">
                                                <button class="btn secondary" type="submit">Enviar comprobante</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if($student->chargePaymentRequests->isNotEmpty())
                        <div class="charge-request-list">
                            <h4 class="section-title section-title-sm charge-request-heading">Solicitudes enviadas</h4>
                            @foreach($student->chargePaymentRequests as $paymentRequest)
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
            @endif
        </section>
    </div>
@empty
    <div class="card">No hay alumnos asociados a este representante.</div>
@endforelse

@push('scripts')
<script>
(function () {
    document.querySelectorAll('.rep-student-card').forEach(function (card) {
        const tabs = Array.from(card.querySelectorAll('.rep-portal-tabs .portal-tab'));
        const panels = Array.from(card.querySelectorAll('.portal-tab-panel'));
        if (!tabs.length || !panels.length) return;

        function activate(target) {
            tabs.forEach((tab) => {
                const active = tab.dataset.tabTarget === target;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.tabPanel === target);
            });
        }

        tabs.forEach((tab) => {
            tab.addEventListener('click', function () {
                activate(tab.dataset.tabTarget);
            });
        });
    });
})();
</script>
@endpush
@endsection
