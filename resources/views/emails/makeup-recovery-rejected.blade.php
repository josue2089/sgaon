<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Comprobante rechazado</h2>
    <p>Hola {{ $makeupRequest->student->full_name }}, revisamos tu comprobante de recuperativa y no pudo ser validado.</p>
    @if($makeupRequest->rejection_reason)
        <p><strong>Motivo:</strong> {{ $makeupRequest->rejection_reason }}</p>
    @endif
    <p>Por favor ingresa al portal, corrige la información y vuelve a subir el comprobante para continuar con la reserva del horario.</p>
</div>
