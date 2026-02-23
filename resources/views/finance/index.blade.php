@extends('layouts.app')
@section('content')
@php
    $chargesTotal = $charges->sum('amount');
    $paymentsTotal = $payments->sum('amount');
    $overdueCount = $charges->where('status', 'overdue')->count();
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Financiero 💳</h1>
        <p class="page-subtitle">Control de cargos, pagos y cobranza</p>
    </div>
</div>

<div class="soft-kpi-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));">
    <div class="soft-kpi"><div class="label">Cargos (página)</div><div class="value">${{ number_format($chargesTotal, 0) }}</div></div>
    <div class="soft-kpi"><div class="label">Pagos recientes</div><div class="value">${{ number_format($paymentsTotal, 0) }}</div></div>
    <div class="soft-kpi"><div class="label">Cuentas en mora</div><div class="value" style="color:#dc2626;">{{ $overdueCount }}</div></div>
    <div class="soft-kpi"><div class="label">Cobertura</div><div class="value" style="color:#2563eb;">{{ $chargesTotal > 0 ? round(($paymentsTotal / $chargesTotal) * 100) : 0 }}%</div></div>
</div>

<div class="grid-2">
<div class="card"><h3 style="font-size:1.9rem; color:#0a1e5e;">Nuevo cargo</h3><form class="stack-sm" method="POST" action="{{ route('finance.charges.store') }}">@csrf
<label>Alumno</label><select name="student_id">@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->full_name }}</option>@endforeach</select>
<label>Concepto</label><input name="concept"><label>Monto</label><input type="number" step="0.01" name="amount"><label>Vencimiento</label><input type="date" name="due_date"><label>Status</label><select name="status">@foreach(['pending','partial','paid','overdue'] as $status)<option>{{ $status }}</option>@endforeach</select><button class="btn" type="submit">Crear cargo</button></form></div>
<div class="card"><h3 style="font-size:1.9rem; color:#0a1e5e;">Registrar pago</h3><form class="stack-sm" method="POST" action="{{ route('finance.payments.store') }}">@csrf
<label>Alumno</label><select name="student_id">@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->full_name }}</option>@endforeach</select>
<label>Cargo</label><select name="charge_id"><option value="">Sin vínculo</option>@foreach($charges as $charge)<option value="{{ $charge->id }}">{{ $charge->student->full_name ?? '' }} - {{ $charge->concept }}</option>@endforeach</select>
<label>Monto</label><input type="number" step="0.01" name="amount"><label>Fecha</label><input type="date" name="paid_at"><label>Método</label><input name="method"><label>Referencia</label><input name="reference"><button class="btn" type="submit">Registrar pago</button></form></div>
</div>

<div class="card"><h3 style="font-size:1.9rem; color:#0a1e5e;">Cuentas por cobrar</h3><table><thead><tr><th>Alumno</th><th>Concepto</th><th>Monto</th><th>Status</th></tr></thead><tbody>@foreach($charges as $charge)<tr><td>{{ $charge->student->full_name ?? '' }}</td><td>{{ $charge->concept }}</td><td>${{ number_format($charge->amount,2) }}</td><td><span class="status-pill {{ $charge->status === 'paid' ? 'success' : ($charge->status === 'overdue' ? 'danger' : 'warn') }}">{{ $charge->status }}</span></td></tr>@endforeach</tbody></table>{{ $charges->links() }}</div>
<div class="card"><h3 style="font-size:1.9rem; color:#0a1e5e;">Pagos recientes / Recibos</h3><table><thead><tr><th>Alumno</th><th>Monto</th><th>Fecha</th><th>Recibo</th></tr></thead><tbody>@foreach($payments as $payment)<tr><td>{{ $payment->student->full_name ?? '' }}</td><td>${{ number_format($payment->amount,2) }}</td><td>{{ $payment->paid_at?->format('Y-m-d') }}</td><td>{{ $payment->receipt->receipt_number ?? '' }}</td></tr>@endforeach</tbody></table></div>
@endsection
