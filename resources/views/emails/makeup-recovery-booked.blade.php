@php($booking = $makeupRequest->booking)
@php($session = $booking?->makeupSession)
<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Recuperativa reservada</h2>
    <p>Hola {{ $makeupRequest->student->full_name }}, tu recuperativa quedó reservada.</p>
    <p><strong>Fecha:</strong> {{ $session?->session_date?->format('d/m/Y') ?? 'N/D' }}<br>
       <strong>Hora:</strong> {{ $session?->starts_at ?? 'N/D' }} - {{ $session?->ends_at ?? 'N/D' }}<br>
       <strong>Profesor:</strong> {{ $session?->teacher?->full_name ?? 'N/D' }}</p>
</div>
