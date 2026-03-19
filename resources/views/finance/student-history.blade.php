@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Historial financiero</h1>
        <p class="page-subtitle">{{ $student->full_name }} · línea de tiempo de cargos, pagos y aplicaciones</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('finance.index', ['student_id' => $student->id]) }}">Ver en financiero</a>
        <a class="btn secondary" href="{{ route('students.show', $student) }}">Ficha del alumno</a>
        <a class="btn secondary" href="{{ route('students.edit', $student) }}">Editar alumno</a>
    </div>
</div>

<div class="card">
    <form method="GET" class="fi-filter-bar history-filter-bar">
        <input class="search" type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" aria-label="Fecha inicial">
        <input class="search" type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" aria-label="Fecha final">
        <button class="btn secondary" type="submit">Filtrar</button>
        <a class="btn ghost" href="{{ route('finance.students.history', $student) }}">Limpiar</a>
    </form>
    @if(($filters['start_date'] ?? null) || ($filters['end_date'] ?? null))
        <div class="table-sub">Rango aplicado: {{ $filters['start_date'] ?: 'Inicio' }} a {{ $filters['end_date'] ?: 'Hoy' }}</div>
    @endif
</div>

<div class="summary-grid">
    <div class="card summary-card">
        <div class="summary-label">Total facturado</div>
        <div class="summary-value">${{ number_format($summary['total_charged'], 2) }}</div>
        <div class="table-sub">{{ $summary['charges_count'] }} cargo(s)</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Total cobrado</div>
        <div class="summary-value">${{ number_format($summary['total_paid'], 2) }}</div>
        <div class="table-sub">{{ $summary['payments_count'] }} pago(s)</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Saldo pendiente</div>
        <div class="summary-value">${{ number_format($summary['outstanding'], 2) }}</div>
        <div class="table-sub">Pendiente acumulado</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Cargos vencidos</div>
        <div class="summary-value">{{ $summary['overdue_count'] }}</div>
        <div class="table-sub">Con fecha vencida</div>
    </div>
</div>

<div class="card timeline-card">
    <div class="timeline-list">
        @forelse($timeline as $item)
            <div class="timeline-item">
                <div class="timeline-dot timeline-dot--{{ $item['type'] }}"></div>
                <div class="timeline-content">
                    <div class="timeline-head">
                        <div>
                            <div class="timeline-title">{{ $item['title'] }}</div>
                            <div class="table-sub">{{ $item['subtitle'] }}</div>
                        </div>
                        <div class="timeline-amount">{{ $item['type'] === 'charge' ? '+' : '-' }}${{ number_format($item['amount'], 2) }}</div>
                    </div>
                    <div class="table-sub">{{ optional($item['date'])->format('d/m/Y H:i') ?: 'N/D' }}</div>
                    <div class="timeline-meta">
                        @foreach($item['meta'] as $label => $value)
                            <div><strong>{{ ucfirst($label) }}:</strong> {{ $value }}</div>
                        @endforeach
                    </div>
                    @if(!empty($item['receipt_id']))
                        <div class="form-actions">
                            <a class="btn secondary" href="{{ route('finance.receipts.show', $item['receipt_id']) }}">Ver recibo</a>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="empty-state-inline">No hay movimientos financieros para este alumno.</div>
        @endforelse
    </div>
</div>
@endsection
