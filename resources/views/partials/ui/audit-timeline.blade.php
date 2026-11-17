{{-- Historial de un registro (y, en la ficha del alumno, de sus cargos, pagos, inscripciones y recuperativas). #451 --}}
<div class="card">
    <h3 class="section-title section-title-sm">Historial</h3>
    @if(($auditLogs ?? collect())->count() === 0)
        <div class="empty-state-inline">Sin movimientos registrados.</div>
    @else
        <ol class="audit-timeline">
            @foreach($auditLogs as $log)
                <li class="audit-timeline-item">
                    <div class="audit-timeline-head">
                        <strong>{{ \App\Support\AuditLabel::action($log->action) }}</strong>
                        <span class="entity-sub">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="entity-sub">
                        {{ \App\Support\AuditLabel::entity($log->auditable_type, $log->auditable_id) }} · {{ $log->user->name ?? 'Sistema' }}
                    </div>
                    @include('partials.ui.audit-changes', ['log' => $log])
                </li>
            @endforeach
        </ol>
    @endif
</div>
