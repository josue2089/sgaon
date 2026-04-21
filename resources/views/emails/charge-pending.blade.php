<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Nuevo cargo pendiente</h2>
    <p>Hola {{ $charge->student->full_name }}, se registró un cargo pendiente en tu cuenta.</p>
    <p><strong>Concepto:</strong> {{ $charge->concept }}</p>
    <p><strong>Monto:</strong> ${{ number_format($charge->amount, 2) }}</p>
    <p><strong>Vencimiento:</strong> {{ $charge->due_date?->format('d/m/Y') ?? 'Sin fecha' }}</p>
    <p>Ingresa al portal para cargar el comprobante de pago y esperar validación administrativa.</p>
    <p><a href="{{ route('portal.student') }}" style="display:inline-block;padding:10px 14px;background:#0a1e5e;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Ir al portal</a></p>
</div>
