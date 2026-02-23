@extends('layouts.app')
@section('content')
<div class="card module-head"><div><h2>Reporte de auditoría</h2><p class="muted">Trazabilidad de acciones críticas del sistema.</p></div></div>
<div class="card"><table><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Payload</th></tr></thead><tbody>
@forelse($logs as $log)
<tr>
<td>{{ $log->created_at }}</td>
<td>{{ $log->user->email ?? 'system' }}</td>
<td>{{ $log->action }}</td>
<td>{{ $log->auditable_type ? class_basename($log->auditable_type).'#'.$log->auditable_id : '-' }}</td>
<td><small>{{ json_encode($log->payload) }}</small></td>
</tr>
@empty
<tr><td colspan="5">Sin registros de auditoría.</td></tr>
@endforelse
</tbody></table>{{ $logs->links() }}</div>
@endsection
