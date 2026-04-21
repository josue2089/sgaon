<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Pago rechazado</h2>
    <p>Hola {{ $paymentRequest->student->full_name }}, no pudimos validar tu comprobante de pago.</p>
    <p><strong>Cargo:</strong> {{ $paymentRequest->charge->concept }}</p>
    @if($paymentRequest->rejection_reason)
        <p><strong>Motivo:</strong> {{ $paymentRequest->rejection_reason }}</p>
    @endif
    <p>Ingresa al portal y vuelve a enviar el comprobante corregido.</p>
    <p><a href="{{ route('portal.student') }}" style="display:inline-block;padding:10px 14px;background:#0a1e5e;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Volver a enviar comprobante</a></p>
</div>
