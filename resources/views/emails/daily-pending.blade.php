<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;max-width:560px;">
    <h2 style="margin:0 0 6px;">Pendientes de hoy</h2>
    <p style="margin:0 0 16px;color:#64748b;">{{ now('America/Caracas')->locale('es')->isoFormat('dddd D [de] MMMM') }} · {{ $scopeLabel }}</p>
    <p style="margin:0 0 12px;">Hola {{ $user->name }}, esto es lo que necesita atención hoy:</p>
    <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
        @foreach($items as $item)
            <tr>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;width:48px;font-size:22px;font-weight:700;color:{{ $item['tone'] === 'danger' ? '#dc2626' : ($item['tone'] === 'warn' ? '#d97706' : '#2563eb') }};">{{ $item['count'] }}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;">
                    <strong>{{ $item['label'] }}</strong><br>
                    <span style="color:#64748b;font-size:13px;">{{ $item['hint'] }}</span>
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap;">
                    <a href="{{ $item['url'] }}" style="color:#0a1e5e;font-weight:700;">{{ $item['action'] }} →</a>
                </td>
            </tr>
        @endforeach
    </table>
    <p style="margin:18px 0 0;"><a href="{{ route('dashboard') }}" style="display:inline-block;padding:10px 14px;background:#0a1e5e;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Abrir el panel</a></p>
    <p style="margin:16px 0 0;color:#94a3b8;font-size:12px;">Recibes este resumen porque eres administrador en la plataforma. Solo se envía los días con pendientes.</p>
</div>
