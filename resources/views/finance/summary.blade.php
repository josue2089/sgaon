@extends('layouts.app')
@section('content')
@php
    use App\Support\MoneyFormat;
    use App\Support\PaymentCurrencyConverter;

    $currency = $summary['currency'];
    $formatAmount = fn (float $amount) => $currency === PaymentCurrencyConverter::CURRENCY_EUR
        ? MoneyFormat::eur($amount)
        : MoneyFormat::usd($amount);
    $vesEquivalent = fn (float $amount) => $vesRate > 0
        ? ($currency === PaymentCurrencyConverter::CURRENCY_EUR
            ? MoneyFormat::ves(\App\Support\PaymentCurrencyConverter::eurVesEquivalent($amount, $vesRate))
            : MoneyFormat::ves(\App\Support\PaymentCurrencyConverter::vesEquivalent($amount, $vesRate)))
        : null;
    $exportBase = request()->query();
    $exportLink = fn (string $report, string $format) => route('finance.summary', array_merge($exportBase, ['export' => "{$report}_{$format}"]));
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Resumen financiero</h1>
        <p class="page-subtitle">
            Facturación, cobranza y proyección por fechas
            @if($vesRate > 0)
                · Tasa BCV {{ $currency }}: Bs {{ MoneyFormat::rate($vesRate) }}
            @endif
        </p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('finance.index') }}">Volver a financiero</a>
    </div>
</div>

<form class="fi-filter-bar card" method="GET" action="{{ route('finance.summary') }}">
    <div>
        <label>Desde</label>
        <input type="date" name="start_date" value="{{ $filters['start_date'] }}">
    </div>
    <div>
        <label>Hasta</label>
        <input type="date" name="end_date" value="{{ $filters['end_date'] }}">
    </div>
    <div>
        <label>Moneda</label>
        <select name="currency">
            <option value="EUR" @selected($filters['currency'] === 'EUR')>EUR</option>
            <option value="USD" @selected($filters['currency'] === 'USD')>USD</option>
        </select>
    </div>
    @if(!empty($showCampusFilter))
        <div>
            <label>Sede</label>
            <select name="campus_id">
                <option value="">Todas las sedes</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected($filters['campus_id'] === (string) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <button class="btn" type="submit">Filtrar</button>
</form>

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Cargos creados', 'value' => $formatAmount($summary['total_invoiced'])])
    @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Cobros realizados', 'value' => $formatAmount($summary['total_collected'])])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Pendiente por cobrar', 'value' => $formatAmount($summary['total_outstanding']), 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'calendar', 'label' => 'Periodos con saldo', 'value' => $summary['projection']->count()])
</div>

@if($vesRate > 0)
    <div class="card table-sub">
        Equivalente en bolívares según tasa BCV vigente:
        Cargos creados {{ $vesEquivalent($summary['total_invoiced']) }},
        Cobros realizados {{ $vesEquivalent($summary['total_collected']) }},
        Pendiente {{ $vesEquivalent($summary['total_outstanding']) }}.
    </div>
@endif

<div class="card">
    <h3 class="section-title section-title-sm">Exportar reportes</h3>
    <p class="table-sub" style="margin-bottom:0.75rem;">Los archivos respetan los filtros de fecha, moneda y sede aplicados arriba.</p>
    <table>
        <thead>
        <tr>
            <th>Reporte</th>
            <th>CSV</th>
            <th>Excel</th>
            <th>PDF</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Cargos creados</td>
            <td><a href="{{ $exportLink('charges', 'csv') }}">Descargar</a></td>
            <td><a href="{{ $exportLink('charges', 'xlsx') }}">Descargar</a></td>
            <td><a href="{{ $exportLink('charges', 'pdf') }}">Descargar</a></td>
        </tr>
        <tr>
            <td>Cobros realizados</td>
            <td><a href="{{ $exportLink('payments', 'csv') }}">Descargar</a></td>
            <td><a href="{{ $exportLink('payments', 'xlsx') }}">Descargar</a></td>
            <td><a href="{{ $exportLink('payments', 'pdf') }}">Descargar</a></td>
        </tr>
        <tr>
            <td>Proyección de cobros</td>
            <td><a href="{{ $exportLink('projection', 'csv') }}">Descargar</a></td>
            <td><a href="{{ $exportLink('projection', 'xlsx') }}">Descargar</a></td>
            <td><a href="{{ $exportLink('projection', 'pdf') }}">Descargar</a></td>
        </tr>
        </tbody>
    </table>
</div>

<div class="card">
    <h3 class="section-title section-title-sm">Proyección de cobros por fecha de vencimiento</h3>
    <table>
        <thead>
        <tr><th>Periodo</th><th>Saldo pendiente</th><th>Equivalente Bs</th></tr>
        </thead>
        <tbody>
        @forelse($summary['projection'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ $formatAmount($row['amount']) }}</td>
                <td>{{ $vesRate > 0 ? $vesEquivalent($row['amount']) : 'N/D' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">
                    <div class="empty-state-inline">No hay saldos pendientes con fecha de vencimiento en el rango seleccionado.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
