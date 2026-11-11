{{--
    Pagos de un alumno: cargos pendientes con envío de comprobante en un modal, solicitudes enviadas
    e historial de pagos con su recibo en PDF. Lo usan el portal del alumno y el del representante. #448
    Variables: $pendingCharges, $chargePaymentRequests, $charges, $payments, $paymentMethods, $bcvRate, $bcvEurRate,
    $paymentRoute y $receiptRoute (closures) y $idPrefix (único por alumno).
    El input de archivo usa accept="image/*,.pdf" sin capture: así el teléfono ofrece la cámara, la galería
    (capturas de Pago Móvil) y los archivos; con capture solo abriría la cámara.
--}}
@if($pendingCharges->isEmpty() && $chargePaymentRequests->isEmpty() && $charges->isEmpty() && $payments->isEmpty())
    <div class="card">
        <div class="empty-state-inline">{{ $emptyText ?? 'No hay movimientos de pagos ni cargos registrados.' }}</div>
    </div>
@else
<div class="card">
    <div class="section-head section-head-tight">
        <h3 class="section-title section-title-md">Pagos pendientes</h3>
        <div class="entity-sub">Toca "Enviar comprobante" y sube la foto o el PDF del pago</div>
    </div>
    <div class="charge-pending-list">
        @forelse($pendingCharges as $charge)
            @php($chargeBalance = \App\Support\FinanceReconcile::outstandingForCharge($charge))
            @php($dialogId = $idPrefix.'charge-pay-dialog-'.$charge->id)
            @php($canSubmitProof = ! $charge->hasPaymentInValidation() && ! $charge->isMakeup())
            <article class="charge-pending-card">
                <div class="charge-pending-head">
                    <div>
                        <div class="table-title">{{ $charge->concept }}</div>
                        <div class="table-sub">Vence: {{ $charge->due_date?->format('d/m/Y') ?? 'N/D' }}</div>
                    </div>
                    <div>@include('partials.ui.status', ['status' => $charge->status])</div>
                </div>
                <div class="charge-pending-meta">
                    <div><strong>Saldo pendiente:</strong> {{ \App\Support\MoneyFormat::formatLedgerAmount($chargeBalance, $charge->currencyCode()) }}</div>
                </div>
                <div class="charge-pending-actions">
                    @if($canSubmitProof)
                        <button type="button" class="btn secondary" onclick="document.getElementById('{{ $dialogId }}').showModal()">Enviar comprobante</button>
                    @else
                        @include('partials.portal.charge-payment-state')
                    @endif
                </div>
            </article>
            @if($canSubmitProof)
            <dialog id="{{ $dialogId }}" class="charge-pay-dialog">
                <div class="charge-pay-dialog-head">
                    <h4 class="charge-pay-dialog-title">Enviar comprobante</h4>
                    <button type="button" class="charge-pay-dialog-close" onclick="document.getElementById('{{ $dialogId }}').close()" aria-label="Cerrar">&times;</button>
                </div>
                <p class="charge-pay-dialog-summary">{{ $charge->concept }} · <strong>{{ \App\Support\MoneyFormat::formatLedgerAmount($chargeBalance, $charge->currencyCode()) }}</strong> pendiente</p>
                <form method="POST" action="{{ $paymentRoute($charge) }}" enctype="multipart/form-data" class="stack-xs charge-pay-form" data-guard-submit>
                    @csrf
                    @include('partials.payment-currency-fields', [
                        'prefix' => $idPrefix.'charge-'.$charge->id,
                        'chargeCurrency' => $charge->currencyCode(),
                        'balanceAmount' => $chargeBalance,
                        'usdExchangeRate' => $bcvRate['rate'] ?? 0,
                        'eurExchangeRate' => $bcvEurRate['rate'] ?? 0,
                        'paymentMethods' => $paymentMethods,
                    ])
                    <div>
                        <label for="{{ $dialogId }}-ref">Referencia</label>
                        <input id="{{ $dialogId }}-ref" name="reference" placeholder="Número de operación o referencia">
                    </div>
                    <div>
                        <label for="{{ $dialogId }}-file">Comprobante (foto o PDF)</label>
                        <input id="{{ $dialogId }}-file" type="file" name="payment_proof" required accept="image/*,.pdf">
                    </div>
                    <div>
                        <label for="{{ $dialogId }}-notes">Observaciones</label>
                        <input id="{{ $dialogId }}-notes" name="notes" placeholder="Opcional">
                    </div>
                    <div class="charge-pay-dialog-footer">
                        <button type="button" class="btn secondary" onclick="document.getElementById('{{ $dialogId }}').close()">Cancelar</button>
                        <button class="btn" type="submit">Enviar comprobante</button>
                    </div>
                </form>
            </dialog>
            @endif
        @empty
            <div class="empty-state-inline">No hay cargos pendientes por pagar.</div>
        @endforelse
    </div>
    @if($chargePaymentRequests->isNotEmpty())
        <div class="charge-request-list">
            <h4 class="section-title section-title-sm charge-request-heading">Comprobantes enviados</h4>
            @foreach($chargePaymentRequests as $paymentRequest)
                <article class="charge-request-card">
                    <div class="charge-request-line">
                        <span class="table-title">{{ $paymentRequest->charge?->concept ?? 'N/D' }}</span>
                        <span>@include('partials.ui.status', ['status' => $paymentRequest->status])</span>
                    </div>
                    <div class="charge-request-meta">
                        <span>{{ $paymentRequest->submitted_at?->format('d/m/Y H:i') ?? 'N/D' }}</span>
                        <span>
                            @if(($paymentRequest->currency ?? 'USD') === 'VES')
                                {{ \App\Support\MoneyFormat::ves((float) ($paymentRequest->original_amount ?? $paymentRequest->amount)) }}
                                · {{ \App\Support\MoneyFormat::usd((float) $paymentRequest->amount) }}
                            @else
                                {{ \App\Support\MoneyFormat::usd((float) $paymentRequest->amount) }}
                            @endif
                        </span>
                    </div>
                    @if($paymentRequest->rejection_reason)
                        <div class="charge-request-reject"><strong>Motivo:</strong> {{ $paymentRequest->rejection_reason }}</div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
<div class="grid-2">
    <div class="card table-card">
        <h4 class="section-title section-title-sm">Cargos</h4>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Concepto</th><th>Monto</th><th>Estado</th></tr></thead><tbody>
        @forelse($charges as $charge)
            <tr><td>{{ $charge->concept }}</td><td>{{ \App\Support\MoneyFormat::chargeAmount($charge, $charge->isEur() ? ($bcvEurRate['rate'] ?? 0) : ($bcvRate['rate'] ?? 0)) }}</td><td>@include('partials.ui.status', ['status' => $charge->status])</td></tr>
        @empty
            <tr><td colspan="3"><div class="empty-state-inline">Sin cargos</div></td></tr>
        @endforelse
        </tbody></table></div>
    </div>
    <div class="card table-card">
        <h4 class="section-title section-title-sm">Pagos y recibos</h4>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>Monto</th><th>Método</th><th>Recibo</th></tr></thead><tbody>
        @forelse($payments as $payment)
            <tr>
                <td>{{ \App\Support\DateFormat::date($payment->paid_at) }}</td>
                <td>{{ \App\Support\MoneyFormat::dualLine($payment) }}</td>
                <td>{{ $payment->method }}</td>
                <td>
                    @if($payment->receipt)
                        <a class="btn secondary" href="{{ $receiptRoute($payment->receipt) }}">PDF {{ $payment->receipt->receipt_number }}</a>
                    @else
                        <span class="table-sub">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><div class="empty-state-inline">Sin pagos</div></td></tr>
        @endforelse
        </tbody></table></div>
    </div>
</div>
@endif
