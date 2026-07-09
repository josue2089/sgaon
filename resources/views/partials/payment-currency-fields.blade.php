@php
    use App\Support\MoneyFormat;
    use App\Support\PaymentCurrencyConverter;

    $prefix = $prefix ?? 'pay';
    $chargeCurrency = strtoupper((string) ($chargeCurrency ?? PaymentCurrencyConverter::CURRENCY_USD));
    $balanceAmount = (float) ($balanceAmount ?? $balanceUsd ?? 0);
    $usdExchangeRate = (float) ($usdExchangeRate ?? $exchangeRate ?? 0);
    $eurExchangeRate = (float) ($eurExchangeRate ?? 0);
    $methodsByCurrency = [
        PaymentCurrencyConverter::CURRENCY_USD => ($paymentMethods ?? collect())->where('currency', PaymentCurrencyConverter::CURRENCY_USD)->values(),
        PaymentCurrencyConverter::CURRENCY_VES => ($paymentMethods ?? collect())->where('currency', PaymentCurrencyConverter::CURRENCY_VES)->values(),
        PaymentCurrencyConverter::CURRENCY_EUR => ($paymentMethods ?? collect())->where('currency', PaymentCurrencyConverter::CURRENCY_EUR)->values(),
    ];
    $methodsPayload = collect($methodsByCurrency)->map(function ($items) {
        return $items->map(fn ($method) => [
            'id' => $method->id,
            'label' => $method->label,
            'lines' => $method->accountSummaryLines(),
        ])->values();
    });
    $availableCurrencies = $chargeCurrency === PaymentCurrencyConverter::CURRENCY_EUR
        ? [PaymentCurrencyConverter::CURRENCY_EUR, PaymentCurrencyConverter::CURRENCY_VES]
        : [PaymentCurrencyConverter::CURRENCY_USD, PaymentCurrencyConverter::CURRENCY_VES];
@endphp
<div
    class="payment-currency-block"
    data-payment-currency-root="{{ $prefix }}"
    data-charge-currency="{{ $chargeCurrency }}"
    data-balance-amount="{{ number_format($balanceAmount, 2, '.', '') }}"
    data-usd-exchange-rate="{{ number_format($usdExchangeRate, 8, '.', '') }}"
    data-eur-exchange-rate="{{ number_format($eurExchangeRate, 8, '.', '') }}"
    data-methods='@json($methodsPayload)'
>
    <div>
        <label for="{{ $prefix }}-currency">Moneda de pago</label>
        <select id="{{ $prefix }}-currency" name="currency" class="payment-currency-select" required>
            @foreach($availableCurrencies as $currencyOption)
                <option value="{{ $currencyOption }}">
                    @if($currencyOption === PaymentCurrencyConverter::CURRENCY_EUR)
                        EUR (euros)
                    @elseif($currencyOption === PaymentCurrencyConverter::CURRENCY_VES)
                        Bs (bolívares)
                    @else
                        USD (dólares)
                    @endif
                </option>
            @endforeach
        </select>
    </div>
    <div class="payment-currency-summary table-sub">
        Saldo pendiente: <strong class="payment-balance-label">
            @if($chargeCurrency === PaymentCurrencyConverter::CURRENCY_EUR)
                {{ MoneyFormat::eur($balanceAmount) }}
            @else
                {{ MoneyFormat::usd($balanceAmount) }}
            @endif
        </strong>
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
        <div class="table-sub payment-ledger-preview" hidden>Equivalente aplicado: <strong>—</strong></div>
    </div>
</div>
