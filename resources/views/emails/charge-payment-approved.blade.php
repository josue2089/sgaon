<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Pago aprobado</h2>
    <p>Hola {{ $paymentRequest->student->full_name }}, aprobamos tu comprobante de pago.</p>
    <p><strong>Cargo:</strong> {{ $paymentRequest->charge->concept }}</p>
    <p><strong>Monto validado:</strong> ${{ number_format($paymentRequest->amount, 2) }}</p>
    <p>Tu pago ya fue aplicado a la cuenta.</p>
    <p><a href="{{ route('portal.student') }}" style="display:inline-block;padding:10px 14px;background:#0a1e5e;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Ver estado en portal</a></p>
</div>
