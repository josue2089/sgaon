@extends('layouts.app')
@section('content')
<div class="card"><h2>Reporte de pagos / CxC</h2><a class="btn secondary" href="{{ route('reports.payments', ['export' => 'csv']) }}">Exportar CSV</a><table><thead><tr><th>Alumno</th><th>Concepto</th><th>Monto</th><th>Status</th><th>Pagado</th></tr></thead><tbody>
@foreach($charges as $charge)
<tr><td>{{ $charge->student->full_name ?? '' }}</td><td>{{ $charge->concept }}</td><td>${{ number_format($charge->amount,2) }}</td><td>{{ $charge->status }}</td><td>${{ number_format($charge->payments->sum('amount'),2) }}</td></tr>
@endforeach
</tbody></table>{{ $charges->links() }}</div>
@endsection
