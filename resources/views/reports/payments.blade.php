@extends('layouts.app')
@section('content')
<div class="card">
    <div class="module-head">
        <div>
            <h1 class="page-title">Reporte de pagos / CxC 💰</h1>
            <p class="page-subtitle">Resumen de cargos y monto efectivamente pagado</p>
        </div>
        <a class="btn secondary" href="{{ route('reports.payments', ['export' => 'csv']) }}">Exportar CSV</a>
    </div>
    <table><thead><tr><th>Alumno</th><th>Concepto</th><th>Monto</th><th>Status</th><th>Pagado</th></tr></thead><tbody>
@foreach($charges as $charge)
<tr><td>{{ $charge->student->full_name ?? '' }}</td><td>{{ $charge->concept }}</td><td>${{ number_format($charge->amount,2) }}</td><td><span class="status-pill {{ $charge->status === 'paid' ? 'success' : ($charge->status === 'overdue' ? 'danger' : 'warn') }}">{{ $charge->status }}</span></td><td>${{ number_format($charge->payments->sum('amount'),2) }}</td></tr>
@endforeach
</tbody></table>
@if($charges->hasPages())
    {{ $charges->links() }}
@endif
</div>
@endsection
