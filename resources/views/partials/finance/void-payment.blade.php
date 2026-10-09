@if($payment->voided_at)
    @include('partials.ui.status-badge', ['tone' => 'danger', 'text' => 'Anulado', 'title' => $payment->void_reason])
@elseif(auth()->user()?->hasPermission('finance.manage'))
    <form method="POST" action="{{ route('finance.payments.void', $payment) }}"
          data-confirm-title="Anular pago" data-confirm="Los cargos que cubría este pago vuelven a quedar con saldo pendiente. Esta acción queda registrada." data-confirm-reason="Motivo de la anulación" data-confirm-ok="Anular pago" data-confirm-danger>
        @csrf
        <input type="hidden" name="reason">
        <button class="btn-link-danger" type="submit">Anular pago</button>
    </form>
@endif
