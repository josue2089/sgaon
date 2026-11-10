@php use App\Support\MoneyFormat; use App\Support\StatusLabel; @endphp
@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Salud de datos</h1>
        <p class="page-subtitle">Verificaciones de solo lectura. Nada de esta página cambia datos, salvo el botón de recalcular.</p>
    </div>
</div>
@include('partials.layout.config-tabs')

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'label' => 'Alumnos activos', 'value' => $summary['active_students'], 'subtitle' => $summary['students'].' en total'])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'label' => 'Inscripciones', 'value' => $summary['enrollments']])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'label' => 'Cargos vigentes', 'value' => MoneyFormat::number($summary['charges_total'])])
    @include('partials.ui.soft-kpi', ['class' => 'centered', 'label' => 'Pagos vigentes', 'value' => MoneyFormat::number($summary['payments_total'])])
</div>

<div class="detail-grid">
    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Estado de los cargos</h2>
            @include('partials.ui.status-badge', ['tone' => $staleCharges > 0 ? 'warn' : 'ok', 'text' => $staleCharges > 0 ? $staleCharges.' por recalcular' : 'Al día'])
        </div>
        <div class="detail-list">
            @foreach($chargeStatuses as $status => $total)
                <div><strong>{{ StatusLabel::label($status) }}:</strong> {{ $total }}</div>
            @endforeach
        </div>
        <p class="entity-sub">Revisa los 2.000 cargos más recientes. Se recalcula solo cada noche; usa el botón si hiciste correcciones a mano.</p>
        @if($lastReconcile)
            <p class="entity-sub">Último recálculo manual: {{ $lastReconcile->created_at->format('d/m/Y H:i') }}.</p>
        @endif
        <form method="POST" action="{{ route('data-health.reconcile-charges') }}" data-guard-submit data-confirm="Se recalcula el estado de todos los cargos según sus pagos y vencimientos. ¿Continuar?">
            @csrf
            <button class="btn secondary" type="submit" data-submit-busy-label="Recalculando…">Recalcular estado de cargos</button>
        </form>
    </div>

    <div class="card">
        <h2 class="section-title section-title-md">Personas</h2>
        <div class="detail-list">
            <div><strong>Profesores activos sin acceso:</strong> {{ $teachersWithoutAccess }} <span class="entity-sub">(se invitan desde su ficha)</span></div>
            <div><strong>Grupos de representantes repetidos por unir:</strong> {{ $representativesToMerge }} <span class="entity-sub">(representatives:merge-duplicates)</span></div>
            <div><strong>Grupos de representantes para revisar a mano:</strong> {{ count($representativesToReview) }}</div>
        </div>
        @if($representativesToReview !== [])
            <details>
                <summary>Ver grupos para revisar</summary>
                <ul>
                    @foreach($representativesToReview as $item)
                        <li>{{ $item['reason'] }}: fichas {{ implode(', ', $item['ids']) }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</div>

<div class="card table-card">
    <h2 class="section-title section-title-md">Cursos con más de un grupo</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th scope="col">Curso</th><th scope="col">Sede</th><th scope="col">Grupos</th></tr></thead>
            <tbody>
            @forelse($duplicateGroups as $row)
                <tr>
                    <td>@if($row['course'])<a href="{{ route('courses.show', $row['course']) }}">{{ $row['course']->name }}</a>@else Curso eliminado @endif</td>
                    <td>{{ $row['course']?->campus?->name ?? '—' }}</td>
                    <td>{{ $row['groups'] }} ({{ $row['group_ids'] }})</td>
                </tr>
            @empty
                <tr><td colspan="3"><div class="empty-state-inline">No hay cursos con grupos duplicados.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
