@extends('layouts.app')
@section('content')
<div class="grid-2">
<div class="card"><h3>Nuevo cargo</h3><form method="POST" action="{{ route('finance.charges.store') }}">@csrf
<label>Alumno</label><select name="student_id">@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->full_name }}</option>@endforeach</select>
<label>Concepto</label><input name="concept"><label>Monto</label><input type="number" step="0.01" name="amount"><label>Vencimiento</label><input type="date" name="due_date"><label>Status</label><select name="status">@foreach(['pending','partial','paid','overdue'] as $status)<option>{{ $status }}</option>@endforeach</select><button class="btn" type="submit">Crear cargo</button></form></div>
<div class="card"><h3>Registrar pago</h3><form method="POST" action="{{ route('finance.payments.store') }}">@csrf
<label>Alumno</label><select name="student_id">@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->full_name }}</option>@endforeach</select>
<label>Cargo</label><select name="charge_id"><option value="">Sin vínculo</option>@foreach($charges as $charge)<option value="{{ $charge->id }}">{{ $charge->student->full_name ?? '' }} - {{ $charge->concept }}</option>@endforeach</select>
<label>Monto</label><input type="number" step="0.01" name="amount"><label>Fecha</label><input type="date" name="paid_at"><label>Método</label><input name="method"><label>Referencia</label><input name="reference"><button class="btn" type="submit">Registrar pago</button></form></div>
</div>
<div class="card"><h3>Cuentas por cobrar</h3><table><thead><tr><th>Alumno</th><th>Concepto</th><th>Monto</th><th>Status</th></tr></thead><tbody>@foreach($charges as $charge)<tr><td>{{ $charge->student->full_name ?? '' }}</td><td>{{ $charge->concept }}</td><td>${{ number_format($charge->amount,2) }}</td><td>{{ $charge->status }}</td></tr>@endforeach</tbody></table>{{ $charges->links() }}</div>
<div class="card"><h3>Pagos recientes / Recibos</h3><table><thead><tr><th>Alumno</th><th>Monto</th><th>Fecha</th><th>Recibo</th></tr></thead><tbody>@foreach($payments as $payment)<tr><td>{{ $payment->student->full_name ?? '' }}</td><td>${{ number_format($payment->amount,2) }}</td><td>{{ $payment->paid_at?->format('Y-m-d') }}</td><td>{{ $payment->receipt->receipt_number ?? '' }}</td></tr>@endforeach</tbody></table></div>
@endsection
