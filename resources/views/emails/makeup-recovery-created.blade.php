@php($course = $makeupRequest->enrollment?->group?->course)
<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Recuperativa pendiente</h2>
    <p>Hola, registramos una inasistencia de <strong>{{ $makeupRequest->student->full_name }}</strong> en <strong>{{ $course?->name ?? 'su curso' }}</strong>{{ $makeupRequest->missedSession?->session_date ? ' el '.$makeupRequest->missedSession->session_date->format('d/m/Y') : '' }}.</p>
    <p>Para recuperar la clase hay que gestionar una recuperativa. Monto actual: <strong>{{ \App\Support\MoneyFormat::usd($makeupRequest->price) }}</strong>.</p>
    <p>Entra al portal, en la pestaña Recuperativas, y carga el comprobante de pago y, si aplica, el reposo médico. Al validarlo se habilita la reserva del horario.</p>
</div>
