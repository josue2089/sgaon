<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Pago validado</h2>
    <p>Hola {{ $makeupRequest->student->full_name }}, tu comprobante de recuperativa fue validado.</p>
    <p>Ya puedes entrar al portal y reservar un horario disponible compatible con tu nivel actual.</p>
    <p><a href="{{ route('portal.student') }}" style="display:inline-block;padding:10px 14px;background:#0a1e5e;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Reservar recuperativa</a></p>
</div>
