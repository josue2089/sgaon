@php use App\Support\MoneyFormat; @endphp
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
        'recuperativas' => $openMakeupCount,
    ];
@endphp

<div class="summary-grid student-summary-grid">
    <div class="card summary-card"><div class="summary-label">Inscripciones</div><div class="summary-value">{{ $enrollments->count() }}</div></div>
    <div class="card summary-card"><div class="summary-label">Recuperativas abiertas</div><div class="summary-value">{{ $openMakeupCount }}</div></div>
    <div class="card summary-card"><div class="summary-label">Cargos pendientes</div><div class="summary-value">{{ $pendingCharges->count() }}</div></div>
    <div class="card summary-card"><div class="summary-label">Saldo pendiente</div>@include('partials.portal.balance')</div>
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
    @include('partials.portal.learning-path', ['path' => \App\Support\LearningPath::for($student)])
    <div class="card" id="renewal-enrollment">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Inscripción al siguiente curso</h2>
            <div class="entity-sub">Cursos habilitados desde recordatorio de renovación</div>
        </div>
        @include('partials.portal.renewal-offers', [
            'offers' => $renewalOffers,
            'renewRoute' => fn ($course) => route('portal.student.renewals.enroll', $course),
            'buttonLabel' => 'Inscribirme en este curso',
        ])
    </div>
</section>

<section class="portal-tab-panel" data-tab-panel="agenda">
    @include('partials.portal.agenda', ['emptyText' => 'No tienes clases próximas ni registros de asistencia todavía.'])
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
                <tr><td>{{ $enrollment->group->course->name ?? '' }}</td><td>{{ $enrollment->group->name ?? '' }}</td><td>@include('partials.ui.status', ['status' => $enrollment->status, 'domain' => 'enrollment'])</td><td>{{ $enrollment->progress }}%</td></tr>
            @endforeach
            </tbody></table></div>
        </div>
    @endif
</section>

<section class="portal-tab-panel" data-tab-panel="recuperativas">
    @include('partials.portal.makeups', [
        'idPrefix' => 'st-',
        'paymentRoute' => fn ($makeupRequest) => route('portal.student.makeups.payment', $makeupRequest),
        'bookRoute' => fn ($makeupRequest) => route('portal.student.makeups.book', $makeupRequest),
        'emptyText' => 'No tienes recuperativas pendientes por gestionar.',
    ])
</section>

<section class="portal-tab-panel" data-tab-panel="pagos">
    @include('partials.portal.charges', [
        'idPrefix' => 'st-',
        'paymentRoute' => fn ($charge) => route('portal.student.charges.payment', $charge),
        'receiptRoute' => fn ($receipt) => route('portal.student.receipts.pdf', $receipt),
        'emptyText' => 'No tienes movimientos de pagos ni cargos registrados.',
    ])
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
