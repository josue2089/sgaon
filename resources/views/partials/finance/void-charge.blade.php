@if($charge->voided_at)
    <span class="status-pill danger" title="{{ $charge->void_reason }}">Anulado</span>
@elseif(auth()->user()?->hasPermission('finance.manage') && \App\Support\FinanceReconcile::paidTotalForCharge($charge) <= 0)
    <form method="POST" action="{{ route('finance.charges.void', $charge) }}"
          onsubmit="const reason = prompt('Motivo de la anulación del cargo:'); if (!reason || !reason.trim()) { return false; } this.reason.value = reason.trim(); return true;">
        @csrf
        <input type="hidden" name="reason">
        <button class="btn-link-danger" type="submit">Anular cargo</button>
    </form>
@elseif(auth()->user()?->hasPermission('finance.manage'))
    <span class="table-sub" title="Primero anule el pago aplicado a este cargo">Con pagos</span>
@endif
