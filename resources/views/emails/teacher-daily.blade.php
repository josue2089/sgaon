<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;max-width:560px;">
    <h2 style="margin:0 0 6px;">Tu día</h2>
    <p style="margin:0 0 16px;color:#64748b;">{{ now()->locale('es')->isoFormat('dddd D [de] MMMM') }}</p>
    <p style="margin:0 0 12px;">Hola {{ $teacher->first_name ?: $user->name }}, este es tu resumen de hoy.</p>

    <h3 style="margin:16px 0 6px;font-size:15px;">Clases de hoy</h3>
    @if($todaySessions->isEmpty())
        <p style="margin:0;color:#64748b;">Hoy no tienes clases.</p>
    @else
        <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
            @foreach($todaySessions as $session)
                <tr>
                    <td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;width:90px;font-weight:700;">{{ $session->starts_at ? substr($session->starts_at, 0, 5) : '' }}{{ $session->ends_at ? ' - '.substr($session->ends_at, 0, 5) : '' }}</td>
                    <td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">
                        <strong>{{ $session->group?->course?->name ?? $session->group?->name }}</strong>
                        @if($session->planned_unit)<br><span style="color:#64748b;font-size:13px;">{{ $session->planned_unit }}</span>@endif
                    </td>
                    <td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap;">
                        <a href="{{ route('attendance.index', ['class_session_id' => $session->id]) }}" style="color:#0a1e5e;font-weight:700;">Asistencia →</a>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($items !== [])
        <h3 style="margin:18px 0 6px;font-size:15px;">Pendientes</h3>
        <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
            @foreach($items as $item)
                <tr>
                    <td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;width:40px;font-size:20px;font-weight:700;color:{{ $item['tone'] === 'danger' ? '#dc2626' : ($item['tone'] === 'warn' ? '#d97706' : '#2563eb') }};">{{ $item['count'] }}</td>
                    <td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;"><strong>{{ $item['label'] }}</strong><br><span style="color:#64748b;font-size:13px;">{{ $item['hint'] }}</span></td>
                    <td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap;"><a href="{{ $item['url'] }}" style="color:#0a1e5e;font-weight:700;">{{ $item['action'] }} →</a></td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($makeupBookings->isNotEmpty())
        <h3 style="margin:18px 0 6px;font-size:15px;">Recuperativas reservadas contigo</h3>
        <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
            @foreach($makeupBookings as $booking)
                <tr>
                    <td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;width:110px;">{{ $booking->makeupSession?->session_date?->format('d/m') }} {{ $booking->makeupSession?->starts_at ? substr($booking->makeupSession->starts_at, 0, 5) : '' }}</td>
                    <td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">{{ $booking->makeupRequest?->student?->full_name }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p style="margin:18px 0 0;"><a href="{{ route('dashboard') }}" style="display:inline-block;padding:10px 14px;background:#0a1e5e;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Abrir el panel</a></p>
    <p style="margin:16px 0 0;color:#94a3b8;font-size:12px;">Recibes este resumen porque eres profesor en la plataforma. Solo se envía los días con clases o pendientes.</p>
</div>
