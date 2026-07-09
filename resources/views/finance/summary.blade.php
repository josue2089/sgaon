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
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Resumen financiero</h1>
        <p class="page-subtitle">
            Facturación, cobranza y proyección por fechas de vencimiento
            @if($vesRate > 0)
                · Tasa BCV {{ $currency }}: Bs {{ number_format($vesRate, 4, ',', '.') }}
            @endif
        </p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('finance.index') }}">Volver a financiero</a>
        <a class="btn secondary" href="{{ route('finance.summary', array_merge(request()->query(), ['export' => 'projection_csv'])) }}">Exportar proyección CSV</a>
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
    <button class="btn" type="submit">Filtrar</button>
</form>

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Total facturado', 'value' => $formatAmount($summary['total_invoiced'])])
    @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Total cobrado', 'value' => $formatAmount($summary['total_collected'])])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Pendiente por cobrar', 'value' => $formatAmount($summary['total_outstanding']), 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'calendar', 'label' => 'Periodos con saldo', 'value' => $summary['projection']->count()])
</div>

@if($vesRate > 0)
    <div class="card table-sub">
        Equivalente en bolívares según tasa BCV vigente:
        Facturado {{ $vesEquivalent($summary['total_invoiced']) }},
        Cobrado {{ $vesEquivalent($summary['total_collected']) }},
        Pendiente {{ $vesEquivalent($summary['total_outstanding']) }}.
    </div>
@endif

<div class="card">
    <h3 class="section-title section-title-sm">Proyección de cobros por fecha</h3>
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
