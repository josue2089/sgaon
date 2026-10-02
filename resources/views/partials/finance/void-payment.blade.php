@if($payment->voided_at)
    <span class="status-pill danger" title="{{ $payment->void_reason }}">Anulado</span>
@elseif(auth()->user()?->hasPermission('finance.manage'))
    <form method="POST" action="{{ route('finance.payments.void', $payment) }}"
          onsubmit="const reason = prompt('Motivo de la anulación del pago:'); if (!reason || !reason.trim()) { return false; } this.reason.value = reason.trim(); return true;">
        @csrf
        <input type="hidden" name="reason">
        <button class="btn-link-danger" type="submit">Anular pago</button>
    </form>
@endif
