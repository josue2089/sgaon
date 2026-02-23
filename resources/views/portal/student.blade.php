@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Portal del Alumno</h2><p><strong>{{ $student->full_name }}</strong> | {{ $student->email }}</p></div></div>
<div class="card"><h3>Inscripciones</h3><table><thead><tr><th>Curso</th><th>Grupo</th><th>Estado</th><th>Progreso</th></tr></thead><tbody>
@forelse($enrollments as $enrollment)
<tr><td>{{ $enrollment->group->course->name ?? '' }}</td><td>{{ $enrollment->group->name ?? '' }}</td><td><span class="status-pill {{ $enrollment->status === 'active' ? 'success' : ($enrollment->status === 'completed' ? 'info' : 'warn') }}">{{ $enrollment->status }}</span></td><td>{{ $enrollment->progress }}%</td></tr>
@empty
<tr><td colspan="4">Sin inscripciones</td></tr>
@endforelse
</tbody></table></div>
<div class="card"><h3>Resumen de Asistencia</h3><table><thead><tr><th>Grupo</th><th>Presente</th><th>Ausente</th><th>Tarde</th><th>Justificada</th></tr></thead><tbody>
@forelse($attendance as $item)
<tr><td>{{ $item->group->name ?? '' }}</td><td>{{ $item->present_count }}</td><td>{{ $item->absent_count }}</td><td>{{ $item->late_count }}</td><td>{{ $item->justified_count }}</td></tr>
@empty
<tr><td colspan="5">Sin registros</td></tr>
@endforelse
</tbody></table></div>
<div class="grid-2">
<div class="card"><h3>Cargos</h3><table><thead><tr><th>Concepto</th><th>Monto</th><th>Estado</th></tr></thead><tbody>@foreach($charges as $charge)<tr><td>{{ $charge->concept }}</td><td>${{ number_format($charge->amount,2) }}</td><td>{{ $charge->status }}</td></tr>@endforeach</tbody></table></div>
<div class="card"><h3>Pagos</h3><table><thead><tr><th>Fecha</th><th>Monto</th><th>Método</th></tr></thead><tbody>@foreach($payments as $payment)<tr><td>{{ $payment->paid_at?->format('Y-m-d') }}</td><td>${{ number_format($payment->amount,2) }}</td><td>{{ $payment->method }}</td></tr>@endforeach</tbody></table></div>
</div>
@endsection
