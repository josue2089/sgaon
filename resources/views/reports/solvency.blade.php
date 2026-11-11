@php use App\Support\MoneyFormat; @endphp
@extends('layouts.app')
@section('content')
{{-- Solvencia por sede: alumnos al día y con deuda, regular y extracurricular. #514 --}}
@php
    $base = array_filter(['campus_id' => $filters['campus_id'], 'month' => $filters['month']]);
    $cellUrl = fn (array $params) => route('reports.solvency', array_merge($base, $params)).'#lista';
    $money = fn (array $amounts) => collect($amounts)->map(fn ($amount, $currency) => $currency === 'EUR' ? MoneyFormat::eur($amount) : MoneyFormat::usd($amount))->implode(' · ') ?: '—';
    $bucketLabels = ['owing' => 'con deuda', 'up_to_date' => 'al día', 'active' => 'activos', 'over_30' => 'con mora de más de 30 días'];
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Solvencia por sede</h1>
        <p class="page-subtitle">Cuántos alumnos están al día y cuántos deben, por sede y tipo de programa. {{ $report['cutLabel'] }}.</p>
    </div>
    <a class="btn secondary" href="{{ route('reports.solvency', array_merge($base, ['export' => 'summary_csv'])) }}">Exportar resumen</a>
</div>
@include('partials.layout.reports-tabs')

<form method="GET" action="{{ route('reports.solvency') }}" class="card">
    <div class="grid-3">
        @if($campuses->count() > 1)
            <div>
                <label for="solvency-campus">Sede</label>
                <select id="solvency-campus" name="campus_id">
                    <option value="">Todas las sedes</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}" @selected((int) $filters['campus_id'] === $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="solvency-month">Mes (opcional)</label>
            <input id="solvency-month" type="month" name="month" value="{{ $filters['month'] }}">
            @error('month')<div class="form-error">{{ $message }}</div>@enderror
        </div>
    </div>
    <p class="entity-sub">Sin mes: deuda vencida hasta hoy. Con mes: quién tiene pagado lo que vence en ese mes.</p>
    <div class="form-actions">
        <button class="btn" type="submit">Ver reporte</button>
        @if($base)
            <a class="btn secondary" href="{{ route('reports.solvency') }}">Quitar filtros</a>
        @endif
    </div>
</form>

<div class="card table-card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Sede</th>
                <th>Tipo</th>
                <th>Alumnos activos</th>
                <th>Al día</th>
                <th>Con deuda</th>
                <th>Mora &gt; 30 días</th>
                <th>Deuda</th>
            </tr>
            </thead>
            <tbody>
            @forelse($report['rows'] as $row)
                @php($params = ['list_campus_id' => $row['campus_id'], 'category' => $row['category']])
                <tr>
                    <td class="table-title">{{ $row['campus_name'] }}</td>
                    <td>{{ $categories[$row['category']] }}</td>
                    <td><a href="{{ $cellUrl($params + ['bucket' => 'active']) }}">{{ $row['active'] }}</a></td>
                    <td><a href="{{ $cellUrl($params + ['bucket' => 'up_to_date']) }}">{{ $row['up_to_date'] }}</a></td>
                    <td>
                        <a href="{{ $cellUrl($params + ['bucket' => 'owing']) }}"><strong>{{ $row['owing'] }}</strong></a>
                        @if($row['owing_inactive'])<div class="table-sub">{{ $row['owing_inactive'] }} sin inscripción activa</div>@endif
                    </td>
                    <td><a href="{{ $cellUrl($params + ['bucket' => 'over_30']) }}">{{ $row['over_30'] }}</a></td>
                    <td>{{ $money($row['amounts']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state-inline">No hay alumnos activos ni deudas en las sedes de tu acceso.</div></td></tr>
            @endforelse
            </tbody>
            @if($report['rows']->count() > 1)
                <tfoot>
                <tr>
                    <th colspan="2">Total</th>
                    <th>{{ $report['totals']['active'] }}</th>
                    <th>{{ $report['totals']['up_to_date'] }}</th>
                    <th>{{ $report['totals']['owing'] }}</th>
                    <th>{{ $report['totals']['over_30'] }}</th>
                    <th>{{ $money($report['totals']['amounts']) }}</th>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <p class="entity-sub">Un alumno inscrito en un programa regular y en uno extracurricular cuenta en las dos filas. "Con deuda" incluye a quien debe aunque ya no esté inscrito.</p>
</div>

<div class="card table-card" id="lista">
    <div class="module-head">
        <h2 class="section-title">
            Alumnos {{ $bucketLabels[$filters['bucket']] }}
            @if($filters['list_campus_id']) · {{ $campuses->firstWhere('id', $filters['list_campus_id'])?->name }} @endif
            @if($filters['category']) · {{ $categories[$filters['category']] }} @endif
            ({{ $list->count() }})
        </h2>
        <a class="btn secondary" href="{{ route('reports.solvency', array_merge($base, array_filter(['list_campus_id' => $filters['list_campus_id'], 'category' => $filters['category'], 'bucket' => $filters['bucket']]), ['export' => 'list_csv'])) }}">Exportar lista</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr><th>Alumno</th><th>Sede / tipo</th><th>Curso</th><th>Representante</th><th>Deuda</th><th>Mora</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($list as $row)
                <tr>
                    <td>
                        <div class="table-title">{{ $row['student']->full_name }}</div>
                        <div class="table-sub">{{ $row['student']->document_id }}{{ $row['active'] ? '' : ' · Sin inscripción activa' }}</div>
                    </td>
                    <td>{{ $row['campus_name'] }}<div class="table-sub">{{ $categories[$row['category']] }}</div></td>
                    <td>{{ implode(', ', $row['courses']) ?: '—' }}</td>
                    <td>{{ $row['representative']?->full_name ?? '—' }}<div class="table-sub">{{ $row['phone'] }}</div></td>
                    <td>
                        {{ $money($row['debt_amounts']) }}
                        @if($row['charges'])<div class="table-sub">{{ \Illuminate\Support\Str::limit(implode(' · ', $row['charges']), 80) }}</div>@endif
                    </td>
                    <td>{{ $row['owes'] ? ($row['max_days'] > 0 ? $row['max_days'].' día(s)' : 'Vence este mes') : 'Al día' }}</td>
                    <td class="table-actions">
                        @if(\Illuminate\Support\Facades\Route::has('finance.index') && auth()->user()->hasPermission('finance.manage'))
                            <a href="{{ route('finance.index', ['student_id' => $row['student']->id]) }}">Finanzas</a>
                        @else
                            <a href="{{ route('students.show', $row['student']) }}">Ficha</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state-inline">No hay alumnos en esta lista.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
