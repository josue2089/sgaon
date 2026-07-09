@php
    use App\Support\MoneyFormat;
    $receipt = $payment->receipt;
    $student = $payment->student;
@endphp
<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Pago registrado</h2>
    <p>Hola{{ $student ? ' '.$student->full_name : '' }}, confirmamos la recepción de tu pago.</p>

    @if($receipt)
        <p><strong>Recibo:</strong> {{ $receipt->receipt_number }}</p>
        <p><strong>Fecha:</strong> {{ ($receipt->issued_at ?? $payment->paid_at)?->format('d/m/Y') ?? 'N/D' }}</p>
    @endif

    <p><strong>Monto:</strong> {{ MoneyFormat::dualLine($payment) }}</p>
    <p><strong>Método:</strong> {{ $payment->method ?: ($payment->paymentMethod?->label ?? 'N/D') }}</p>

    @if($payment->reference)
        <p><strong>Referencia:</strong> {{ $payment->reference }}</p>
    @endif

    @if($payment->allocations->isNotEmpty())
        <p><strong>Cargos aplicados:</strong></p>
        <ul style="padding-left:18px;margin:0 0 12px;">
            @foreach($payment->allocations as $allocation)
                <li>{{ $allocation->charge?->concept ?? 'Cargo' }} — {{ MoneyFormat::formatLedgerAmount((float) $allocation->amount_applied, $allocation->charge?->currencyCode()) }}</li>
            @endforeach
        </ul>
    @elseif($payment->charge)
        <p><strong>Cargo:</strong> {{ $payment->charge->concept }}</p>
    @endif

    <p>Adjuntamos el recibo en PDF para tus registros.</p>
</div>
