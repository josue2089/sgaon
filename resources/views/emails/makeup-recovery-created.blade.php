@php($course = $makeupRequest->enrollment?->group?->course)
<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Recuperativa pendiente</h2>
    <p>Hola {{ $makeupRequest->student->full_name }}, registramos una inasistencia en <strong>{{ $course?->name ?? 'tu curso' }}</strong>.</p>
    <p>Debes gestionar una clase recuperativa. Monto actual: <strong>{{ \App\Support\MoneyFormat::usd($makeupRequest->price) }}</strong>.</p>
    <p>Ingresa al portal, carga tu comprobante de pago y, si aplica, el reposo médico para habilitar la reserva del horario.</p>
</div>
