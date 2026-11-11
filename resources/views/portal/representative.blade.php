@php use App\Support\MoneyFormat; @endphp
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
        $data = $portalDataByStudentId[$student->id];
        $pendingCharges = $data['pendingCharges'];
        $tabCounts = [
            'inscripciones' => $student->enrollments->count(),
            'evaluaciones' => $gradeEntries->count(),
            'agenda' => $data['agenda']->count(),
            'pagos' => $pendingCharges->count(),
            'recuperativas' => $data['openMakeupCount'],
        ];
        $idPrefix = 'rep-'.$student->id.'-';
    @endphp

    <div class="card rep-student-card">
        <div class="section-head section-head-tight">
            <div>
                <h3 class="section-title section-title-md">{{ $student->full_name }}</h3>
                <div class="entity-sub">Estado: @include('partials.ui.status', ['status' => $student->status])</div>
            </div>
        </div>

        <div class="summary-grid student-summary-grid">
            <div class="card summary-card">
                <div class="summary-label">Inscripciones</div>
                <div class="summary-value">{{ $tabCounts['inscripciones'] }}</div>
            </div>
            <div class="card summary-card">
                <div class="summary-label">Recuperativas abiertas</div>
                <div class="summary-value">{{ $tabCounts['recuperativas'] }}</div>
            </div>
            <div class="card summary-card">
                <div class="summary-label">Cargos pendientes</div>
                <div class="summary-value">{{ $tabCounts['pagos'] }}</div>
            </div>
            <div class="card summary-card">
                <div class="summary-label">Saldo pendiente</div>
                @include('partials.portal.balance', ['balance' => $data['balance']])
            </div>
        </div>

        <div class="portal-tabs rep-portal-tabs" role="tablist" aria-label="Secciones de {{ $student->full_name }}">
            <button type="button" class="portal-tab is-active" data-tab-target="resumen" role="tab" aria-selected="true">Resumen</button>
            <button type="button" class="portal-tab" data-tab-target="inscripciones" role="tab" aria-selected="false">Inscripciones <span class="portal-tab-badge">{{ $tabCounts['inscripciones'] }}</span></button>
            <button type="button" class="portal-tab" data-tab-target="evaluaciones" role="tab" aria-selected="false">Evaluaciones <span class="portal-tab-badge">{{ $tabCounts['evaluaciones'] }}</span></button>
            <button type="button" class="portal-tab" data-tab-target="agenda" role="tab" aria-selected="false">Agenda y asistencia <span class="portal-tab-badge">{{ $tabCounts['agenda'] }}</span></button>
            <button type="button" class="portal-tab" data-tab-target="pagos" role="tab" aria-selected="false">Pagos <span class="portal-tab-badge">{{ $tabCounts['pagos'] }}</span></button>
            <button type="button" class="portal-tab" data-tab-target="recuperativas" role="tab" aria-selected="false">Recuperativas <span class="portal-tab-badge">{{ $tabCounts['recuperativas'] }}</span></button>
        </div>

        <section class="portal-tab-panel is-active" data-tab-panel="resumen">
            @include('partials.portal.learning-path', ['path' => \App\Support\LearningPath::for($student)])
            @if(($renewalOffersByStudentId[$student->id] ?? collect())->isNotEmpty())
                <div class="card">
                    <h4 class="section-title section-title-sm">Inscripción al siguiente curso</h4>
                    @include('partials.portal.renewal-offers', [
                        'offers' => $renewalOffersByStudentId[$student->id],
                        'renewRoute' => fn ($course) => route('portal.representative.renewals.enroll', [$student, $course]),
                        'buttonLabel' => 'Renovar a '.$student->first_name,
                    ])
                </div>
            @endif
            <div class="grid-2">
                <div class="card table-card">
                    <h4 class="section-title section-title-sm">Resumen financiero</h4>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Cargo</th><th>Vence</th><th>Pendiente</th></tr></thead>
                            <tbody>
                                @forelse($pendingCharges as $charge)
                                    <tr>
                                        <td>{{ $charge->concept }}</td>
                                        <td>{{ $charge->due_date?->format('d/m/Y') ?? 'N/D' }}</td>
                                        <td>{{ MoneyFormat::formatLedgerAmount(\App\Support\FinanceReconcile::outstandingForCharge($charge), $charge->currencyCode()) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3"><div class="empty-state-inline">Sin saldo pendiente.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card table-card">
                    <h4 class="section-title section-title-sm">Última actividad</h4>
                    @if($data['chargePaymentRequests']->isNotEmpty())
                        @php($latestRequest = $data['chargePaymentRequests']->sortByDesc('submitted_at')->first())
                        <div class="table-sub">Último comprobante: {{ $latestRequest->submitted_at?->format('d/m/Y H:i') ?? 'N/D' }}</div>
                        <div class="table-sub">Estado: {{ \App\Support\StatusLabel::label($latestRequest->status) }}</div>
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
                            <thead><tr><th>Curso</th><th>Grupo</th><th>Estado</th><th>Bitácora</th></tr></thead>
                            <tbody>
                                @foreach($student->enrollments as $enrollment)
                                    <tr>
                                        <td>{{ $enrollment->group->course->name ?? '' }}</td>
                                        <td>{{ $enrollment->group->name ?? '' }}</td>
                                        <td>{{ \App\Support\StatusLabel::label($enrollment->status, 'enrollment') }}</td>
                                        <td>@if($enrollment->group_id)<a href="{{ route('portal.representative.logbook.pdf', $enrollment) }}">PDF</a>@endif</td>
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

        <section class="portal-tab-panel" data-tab-panel="agenda">
            @include('partials.portal.agenda', $data + ['emptyText' => $student->first_name.' no tiene clases próximas ni registros de asistencia todavía.'])
        </section>

        <section class="portal-tab-panel" data-tab-panel="pagos">
            @include('partials.portal.charges', $data + [
                'idPrefix' => $idPrefix,
                'paymentRoute' => fn ($charge) => route('portal.representative.charges.payment', $charge),
                'receiptRoute' => fn ($receipt) => route('portal.representative.receipts.pdf', $receipt),
                'emptyText' => 'No hay cargos ni pagos registrados para '.$student->first_name.'.',
            ])
        </section>

        <section class="portal-tab-panel" data-tab-panel="recuperativas">
            @include('partials.portal.makeups', $data + [
                'idPrefix' => $idPrefix,
                'paymentRoute' => fn ($makeupRequest) => route('portal.representative.makeups.payment', $makeupRequest),
                'bookRoute' => fn ($makeupRequest) => route('portal.representative.makeups.book', $makeupRequest),
                'emptyText' => $student->first_name.' no tiene recuperativas pendientes.',
            ])
        </section>
    </div>
@empty
    <div class="card">No hay alumnos asociados a este representante.</div>
@endforelse

@push('scripts')
<script>
document.querySelectorAll('dialog.charge-pay-dialog').forEach(function (dlg) {
    dlg.addEventListener('click', function (e) {
        if (e.target === dlg) dlg.close();
    });
});

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
