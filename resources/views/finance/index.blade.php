@extends('layouts.app')
@section('content')
@php
    $chargesTotal = $charges->sum('amount');
    $paymentsTotal = $payments->sum('amount');
    $overdueCount = $charges->where('status', 'overdue')->count();
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Financiero</h1>
        <p class="page-subtitle">Control de cargos, pagos y cobranza</p>
    </div>
</div>

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Cargos (página)', 'value' => '$'.number_format($chargesTotal, 0)])
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Pagos recientes', 'value' => '$'.number_format($paymentsTotal, 0)])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Cuentas en mora', 'value' => $overdueCount, 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Cobertura', 'value' => ($chargesTotal > 0 ? round(($paymentsTotal / $chargesTotal) * 100) : 0).'%', 'valueClass' => 'value-blue'])
</div>

<div class="grid-2">
    <div class="card">
        <h3 class="section-title section-title-sm">Nuevo cargo</h3>
        <form class="stack-sm" method="POST" action="{{ route('finance.charges.store') }}">
            @csrf
            <div>
                <label>Alumno</label>
                <select name="student_id">
                    @foreach($students as $student)
                        <option value="{{ $student->id }}">{{ $student->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Concepto</label>
                <input name="concept">
            </div>
            <div>
                <label>Monto</label>
                <input type="number" step="0.01" name="amount">
            </div>
            <div>
                <label>Vencimiento</label>
                <input type="date" name="due_date">
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    @foreach(['pending','partial','paid','overdue'] as $status)
                        <option>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn" type="submit">Crear cargo</button>
        </form>
    </div>

    <div class="card">
        <h3 class="section-title section-title-sm">Registrar pago</h3>
        <form class="stack-sm" method="POST" action="{{ route('finance.payments.store') }}">
            @csrf
            <div>
                <label>Alumno</label>
                <select name="student_id">
                    @foreach($students as $student)
                        <option value="{{ $student->id }}">{{ $student->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Cargo</label>
                <select name="charge_id">
                    <option value="">Sin vínculo</option>
                    @foreach($charges as $charge)
                        <option value="{{ $charge->id }}">{{ $charge->student->full_name ?? '' }} - {{ $charge->concept }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Monto</label>
                <input type="number" step="0.01" name="amount">
            </div>
            <div>
                <label>Fecha</label>
                <input type="date" name="paid_at">
            </div>
            <div>
                <label>Método</label>
                <input name="method">
            </div>
            <div>
                <label>Referencia</label>
                <input name="reference">
            </div>
            <button class="btn" type="submit">Registrar pago</button>
        </form>
    </div>
</div>

<div class="card">
    <h3 class="section-title section-title-sm">Cuentas por cobrar</h3>
    <table>
        <thead>
        <tr><th>Alumno</th><th>Concepto</th><th>Monto</th><th>Status</th></tr>
        </thead>
        <tbody>
        @foreach($charges as $charge)
            <tr>
                <td>{{ $charge->student->full_name ?? '' }}</td>
                <td>{{ $charge->concept }}</td>
                <td>${{ number_format($charge->amount,2) }}</td>
                <td><span class="status-pill {{ $charge->status === 'paid' ? 'success' : ($charge->status === 'overdue' ? 'danger' : 'warn') }}">{{ $charge->status }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if($charges->hasPages())
        {{ $charges->links() }}
    @endif
</div>

<div class="card">
    <h3 class="section-title section-title-sm">Pagos recientes / Recibos</h3>
    <table>
        <thead>
        <tr><th>Alumno</th><th>Monto</th><th>Fecha</th><th>Recibo</th></tr>
        </thead>
        <tbody>
        @foreach($payments as $payment)
            <tr>
                <td>{{ $payment->student->full_name ?? '' }}</td>
                <td>${{ number_format($payment->amount,2) }}</td>
                <td>{{ $payment->paid_at?->format('Y-m-d') }}</td>
                <td>{{ $payment->receipt->receipt_number ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
