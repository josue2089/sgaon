@if($charge->voided_at)
    @include('partials.ui.status-badge', ['tone' => 'danger', 'text' => 'Anulado', 'title' => $charge->void_reason])
@elseif(auth()->user()?->hasPermission('finance.manage') && \App\Support\FinanceReconcile::paidTotalForCharge($charge) <= 0)
    <form method="POST" action="{{ route('finance.charges.void', $charge) }}"
          data-confirm-title="Anular cargo" data-confirm="El cargo dejará de contar en el saldo del alumno. Esta acción queda registrada." data-confirm-reason="Motivo de la anulación" data-confirm-ok="Anular cargo" data-confirm-danger>
        @csrf
        <input type="hidden" name="reason">
        <button class="btn-link-danger" type="submit">Anular cargo</button>
    </form>
@elseif(auth()->user()?->hasPermission('finance.manage'))
    <span class="table-sub" title="Primero anule el pago aplicado a este cargo">Con pagos</span>
@endif
