@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Recibo {{ $receipt->receipt_number }}</h1>
        <p class="page-subtitle">Detalle exacto de cargos aplicados por este pago</p>
    </div>
    <div class="form-actions">
        <button class="btn secondary" type="button" onclick="window.print()">Imprimir</button>
        <a class="btn secondary" href="{{ route('finance.receipts.pdf', $receipt) }}">Exportar PDF</a>
        <a class="btn secondary" href="{{ route('finance.students.history', $payment->student) }}">Historial del alumno</a>
        <a class="btn secondary" href="{{ route('finance.index') }}">Volver</a>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <h2 class="section-title">Datos del recibo</h2>
        <div class="detail-list">
            <div><strong>Número:</strong> {{ $receipt->receipt_number }}</div>
            <div><strong>Alumno:</strong> {{ $payment->student->full_name ?? 'N/D' }}</div>
            <div><strong>Fecha:</strong> {{ $receipt->issued_at?->format('d/m/Y') ?? 'N/D' }}</div>
            <div><strong>Monto pagado:</strong> {{ \App\Support\MoneyFormat::dualLine($payment) }}</div>
            <div><strong>Método:</strong> {{ $payment->method ?: 'Sin método' }}</div>
            <div><strong>Referencia:</strong> {{ $payment->reference ?: 'Sin referencia' }}</div>
            <div><strong>Recibido por:</strong> {{ $payment->receivedBy?->name ?? 'N/D' }}</div>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Resumen de aplicación</h2>
        <div class="detail-list">
            <div><strong>Total cargos impactados:</strong> {{ $allocations->count() }}</div>
            <div><strong>Total aplicado:</strong> {{ \App\Support\MoneyFormat::formatLedgerAmount($allocations->sum(fn ($item) => (float) ($item->amount_applied ?? 0)), $payment->currency) }}</div>
            <div><strong>Observación:</strong> {{ $payment->notes ?: 'Sin observaciones' }}</div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title">Cargos aplicados</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Concepto</th>
                <th>Curso</th>
                <th>Grupo</th>
                <th>Período</th>
                <th>Monto aplicado</th>
            </tr>
            </thead>
            <tbody>
            @foreach($allocations as $allocation)
                <tr>
                    <td>{{ $allocation->charge->concept ?? 'Cargo legacy' }}</td>
                    <td>{{ $allocation->charge->course->name ?? 'Sin curso' }}</td>
                    <td>{{ $allocation->charge->group->name ?? 'Sin grupo' }}</td>
                    <td>{{ $allocation->charge->period->code ?? ($allocation->charge->billing_period_label ?? 'Sin período') }}</td>
                    <td>{{ \App\Support\MoneyFormat::formatLedgerAmount((float) ($allocation->amount_applied ?? 0), $allocation->charge?->currency) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
