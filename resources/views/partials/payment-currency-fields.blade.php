@php
    use App\Support\MoneyFormat;
    use App\Support\PaymentCurrencyConverter;

    $prefix = $prefix ?? 'pay';
    $balanceUsd = (float) ($balanceUsd ?? 0);
    $exchangeRate = (float) ($exchangeRate ?? 0);
    $methodsByCurrency = [
        PaymentCurrencyConverter::CURRENCY_USD => ($paymentMethods ?? collect())->where('currency', PaymentCurrencyConverter::CURRENCY_USD)->values(),
        PaymentCurrencyConverter::CURRENCY_VES => ($paymentMethods ?? collect())->where('currency', PaymentCurrencyConverter::CURRENCY_VES)->values(),
    ];
    $methodsPayload = collect($methodsByCurrency)->map(function ($items) {
        return $items->map(fn ($method) => [
            'id' => $method->id,
            'label' => $method->label,
            'lines' => $method->accountSummaryLines(),
        ])->values();
    });
@endphp
<div
    class="payment-currency-block"
    data-payment-currency-root="{{ $prefix }}"
    data-balance-usd="{{ number_format($balanceUsd, 2, '.', '') }}"
    data-exchange-rate="{{ number_format($exchangeRate, 8, '.', '') }}"
    data-methods='@json($methodsPayload)'
>
    <div>
        <label for="{{ $prefix }}-currency">Moneda de pago</label>
        <select id="{{ $prefix }}-currency" name="currency" class="payment-currency-select" required>
            <option value="{{ PaymentCurrencyConverter::CURRENCY_USD }}">USD (dólares)</option>
            <option value="{{ PaymentCurrencyConverter::CURRENCY_VES }}">Bs (bolívares)</option>
        </select>
    </div>
    <div class="payment-currency-summary table-sub">
        Saldo pendiente: <strong>{{ MoneyFormat::usd($balanceUsd) }}</strong>
        <span class="payment-currency-ves-hint" hidden>
            · equivalente sugerido <strong class="payment-currency-ves-equivalent">—</strong>
        </span>
    </div>
    <div class="payment-method-accounts card" style="padding:.75rem;margin:.5rem 0;">
        <div class="table-sub" style="margin-bottom:.35rem;">Datos para pagar</div>
        <div class="payment-method-account-lines table-sub">Selecciona un método de pago.</div>
    </div>
    <div>
        <label for="{{ $prefix }}-payment-method">Método de pago</label>
        <select id="{{ $prefix }}-payment-method" name="payment_method_id" class="payment-method-select" required>
            <option value="">Selecciona método</option>
        </select>
    </div>
    <div>
        <label for="{{ $prefix }}-original-amount">Monto que pagaste</label>
        <input
            id="{{ $prefix }}-original-amount"
            class="payment-original-amount"
            type="number"
            step="0.01"
            min="0.01"
            name="original_amount"
            required
            inputmode="decimal"
        >
        <div class="table-sub payment-usd-preview" hidden>Equivalente aplicado: <strong>—</strong></div>
    </div>
</div>
