<div class="card">
    <h3 class="section-title section-title-sm">Auditoría reciente</h3>
    @if(($auditLogs ?? collect())->count() === 0)
        <div class="empty-state-inline">Sin movimientos auditados para este registro.</div>
    @else
        <div class="stack-sm">
            @foreach($auditLogs as $log)
                <div style="padding:0.7rem 0.9rem;border:2px solid var(--line);border-radius:14px;">
                    <div style="display:flex;justify-content:space-between;gap:.8rem;flex-wrap:wrap;">
                        <strong>{{ $log->action }}</strong>
                        <span class="entity-sub">{{ $log->created_at?->format('Y-m-d H:i') }}</span>
                    </div>
                    <div class="entity-sub">Usuario: {{ $log->user->name ?? 'Sistema' }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
