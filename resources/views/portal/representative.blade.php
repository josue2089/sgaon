@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Portal del Representante</h2><p><strong>{{ $representative->first_name }} {{ $representative->last_name }}</strong> | {{ $representative->email }}</p></div></div>
@forelse($students as $student)
<div class="card">
<h3>{{ $student->full_name }}</h3>
<p class="muted">Estado: <span class="status-pill {{ $student->status === 'active' ? 'success' : 'warn' }}">{{ $student->status }}</span></p>
<div class="grid-2">
<div>
<h4>Inscripciones</h4>
<table><thead><tr><th>Curso</th><th>Grupo</th><th>Estado</th></tr></thead><tbody>@foreach($student->enrollments as $enrollment)<tr><td>{{ $enrollment->group->course->name ?? '' }}</td><td>{{ $enrollment->group->name ?? '' }}</td><td>{{ $enrollment->status }}</td></tr>@endforeach</tbody></table>
</div>
<div>
<h4>Resumen financiero</h4>
<table><thead><tr><th>Cargos</th><th>Pagos</th><th>Saldo</th></tr></thead><tbody><tr><td>${{ number_format($student->charges->sum('amount'),2) }}</td><td>${{ number_format($student->payments->sum('amount'),2) }}</td><td>${{ number_format($student->charges->sum('amount') - $student->payments->sum('amount'),2) }}</td></tr></tbody></table>
</div>
<div style="margin-top:1rem;">
<h4>Pagos pendientes</h4>
<table>
    <thead><tr><th>Cargo</th><th>Monto</th><th>Estado</th><th>Comprobante</th></tr></thead>
    <tbody>
    @php($pendingCharges = $student->charges->whereIn('status', ['pending', 'partial', 'overdue']))
    @forelse($pendingCharges as $charge)
        <tr>
            <td>{{ $charge->concept }}</td>
            <td>${{ number_format($charge->amount, 2) }}</td>
            <td>{{ ucfirst($charge->status) }}</td>
            <td>
                <form method="POST" action="{{ route('portal.representative.charges.payment', $charge) }}" enctype="multipart/form-data" class="stack-xs">
                    @csrf
                    <input type="number" step="0.01" min="0.01" max="{{ number_format($charge->amount, 2, '.', '') }}" name="amount" placeholder="Monto" required>
                    <input name="payment_method" placeholder="Método">
                    <input name="reference" placeholder="Referencia">
                    <input type="file" name="payment_proof" required>
                    <input name="notes" placeholder="Observaciones">
                    <button class="btn secondary" type="submit">Enviar comprobante</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="4">Sin cargos pendientes.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@if($student->chargePaymentRequests->isNotEmpty())
<div style="margin-top:1rem;">
<h4>Solicitudes enviadas</h4>
<table>
    <thead><tr><th>Fecha</th><th>Cargo</th><th>Monto</th><th>Estado</th><th>Motivo</th></tr></thead>
    <tbody>
    @foreach($student->chargePaymentRequests as $paymentRequest)
        <tr>
            <td>{{ $paymentRequest->submitted_at?->format('d/m/Y H:i') ?? 'N/D' }}</td>
            <td>{{ $paymentRequest->charge?->concept ?? 'N/D' }}</td>
            <td>${{ number_format($paymentRequest->amount, 2) }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $paymentRequest->status)) }}</td>
            <td>{{ $paymentRequest->rejection_reason ?: 'N/A' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif
</div>
</div>
@empty
<div class="card">No hay alumnos asociados a este representante.</div>
@endforelse
@endsection
