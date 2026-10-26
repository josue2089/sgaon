{{-- Saldo pendiente por moneda con equivalente en Bs. a la tasa BCV vigente. #436 --}}
@php
    use App\Support\MoneyFormat;
    use App\Support\PaymentCurrencyConverter;
    $rateFor = fn (string $currency) => $currency === PaymentCurrencyConverter::CURRENCY_EUR ? ($bcvEurRate ?? []) : ($bcvRate ?? []);
@endphp
@if($balance === [])
    <div class="summary-value">{{ MoneyFormat::usd(0) }}</div>
    <div class="table-sub">Sin saldo pendiente</div>
@else
    @foreach($balance as $currency => $amount)
        @php($rate = (float) ($rateFor($currency)['rate'] ?? 0))
        <div class="summary-value">{{ MoneyFormat::formatLedgerAmount($amount, $currency) }}</div>
        @if($rate > 0)
            <div class="table-sub">≈ {{ MoneyFormat::ves(round($amount * $rate, 2)) }}</div>
        @endif
    @endforeach
    @php($rateDate = ($bcvRate['effective_at'] ?? null) ?: ($bcvEurRate['effective_at'] ?? null))
    @if($rateDate)
        <div class="table-sub">Tasa BCV del {{ $rateDate->format('d/m/Y') }}</div>
    @endif
@endif
