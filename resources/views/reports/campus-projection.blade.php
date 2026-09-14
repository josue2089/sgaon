@php use App\Support\MoneyFormat; @endphp
@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">💰 Proyección de mensualidades por sede</h1>
        <p class="page-subtitle">
            Tarifa vigente: <strong>{{ MoneyFormat::usd($fee) }}</strong> por inscripción activa · mes.
            Basado en inscripciones activas al {{ $generatedAt->locale('es')->isoFormat('D [de] MMMM YYYY, HH:mm') }}.
        </p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('settings.billing.edit') }}">Editar tarifa</a>
        <a class="btn secondary" href="{{ route('reports.campus-projection', ['export' => 'csv']) }}">Exportar CSV</a>
    </div>
</div>

<div class="metric-grid" style="grid-template-columns:repeat(4,minmax(0,1fr)); margin-bottom:1rem;">
    <div class="metric-card metric-blue">
        <div class="metric-label">Alumnos únicos activos</div>
        <div class="metric-value">{{ number_format($totalStudents, 0, ',', '.') }}</div>
    </div>
    <div class="metric-card metric-purple">
        <div class="metric-label">Inscripciones activas</div>
        <div class="metric-value">{{ number_format($totalEnrollments, 0, ',', '.') }}</div>
    </div>
    <div class="metric-card metric-green">
        <div class="metric-label">Proyección mensual total</div>
        <div class="metric-value">{{ MoneyFormat::usd($totalMonthly) }}</div>
    </div>
    <div class="metric-card metric-orange">
        <div class="metric-label">Proyección anual (× 10 meses)</div>
        <div class="metric-value">{{ MoneyFormat::usd($totalAnnual) }}</div>
    </div>
</div>

<div class="card table-card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sede</th>
                    <th>Código</th>
                    <th class="amount">Alumnos únicos activos</th>
                    <th class="amount">Inscripciones activas</th>
                    <th class="amount">Tarifa mensual</th>
                    <th class="amount">Proyección mensual</th>
                    <th class="amount">Proyección anual</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="table-title">{{ $row['campus']->name }}</td>
                        <td>{{ $row['campus']->code }}</td>
                        <td class="amount">{{ number_format($row['unique_students'], 0, ',', '.') }}</td>
                        <td class="amount">{{ number_format($row['active_enrollments'], 0, ',', '.') }}</td>
                        <td class="amount">{{ MoneyFormat::usd($fee) }}</td>
                        <td class="amount">{{ MoneyFormat::usd($row['monthly_projection']) }}</td>
                        <td class="amount">{{ MoneyFormat::usd($row['annual_projection']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">No hay sedes disponibles para tu acceso.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="2">Totales</th>
                        <th class="amount">{{ number_format($totalStudents, 0, ',', '.') }}</th>
                        <th class="amount">{{ number_format($totalEnrollments, 0, ',', '.') }}</th>
                        <th class="amount">—</th>
                        <th class="amount">{{ MoneyFormat::usd($totalMonthly) }}</th>
                        <th class="amount">{{ MoneyFormat::usd($totalAnnual) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<p class="page-subtitle" style="margin-top:1rem;">
    <strong>Notas:</strong> la proyección anual asume 10 meses académicos.
    Cambia la tarifa desde <a href="{{ route('settings.billing.edit') }}">Ajustes → Tarifa mensual</a>.
    Un alumno con varias inscripciones activas (por ejemplo Inglés + Robótica) suma una mensualidad por cada inscripción.
</p>
@endsection
