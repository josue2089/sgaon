@php($session = $makeupRequest->booking?->makeupSession)
@php($fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') : 'N/D')
@php($fmtTime = fn ($time) => $time ? substr((string) $time, 0, 5) : '')
<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    @if($change === 'cancelled')
        <h2 style="margin:0 0 12px;">Recuperativa cancelada</h2>
        <p>Hola, la clase recuperativa de <strong>{{ $makeupRequest->student->full_name }}</strong> del
            <strong>{{ $fmtDate($before['date'] ?? null) }}</strong> a las {{ $fmtTime($before['starts_at'] ?? null) }} fue cancelada por la academia.</p>
        <p>La recuperativa sigue pagada y disponible: puede reservar otro horario desde el portal.</p>
    @else
        <h2 style="margin:0 0 12px;">Cambio de horario de la recuperativa</h2>
        <p>Hola, la clase recuperativa de <strong>{{ $makeupRequest->student->full_name }}</strong> cambió de horario.</p>
        <p><strong>Antes:</strong> {{ $fmtDate($before['date'] ?? null) }} · {{ $fmtTime($before['starts_at'] ?? null) }} - {{ $fmtTime($before['ends_at'] ?? null) }}<br>
           <strong>Ahora:</strong> {{ $fmtDate($session?->session_date) }} · {{ $fmtTime($session?->starts_at) }} - {{ $fmtTime($session?->ends_at) }}<br>
           <strong>Profesor:</strong> {{ $session?->teacher?->full_name ?? 'N/D' }}</p>
        <p>Si el nuevo horario no le sirve, puede escribirnos para cambiarlo.</p>
    @endif
</div>
