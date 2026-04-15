<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recordatorio de inscripcion</title>
</head>
<body style="margin:0;padding:0;background:#eef4ff;font-family:Arial,Helvetica,sans-serif;color:#172554;">
    <div style="max-width:680px;margin:0 auto;padding:36px 18px;">
        <div style="background:linear-gradient(135deg,#102768 0%,#1b3f9d 100%);border-radius:28px 28px 0 0;padding:28px 28px 22px;color:#ffffff;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td style="vertical-align:middle;">
                        <img src="{{ asset('images/logo.png') }}" alt="ON English" style="width:92px;height:auto;display:block;">
                    </td>
                    <td style="text-align:right;vertical-align:middle;">
                        <div style="font-size:26px;font-weight:800;line-height:1;">ON English</div>
                        <div style="font-size:14px;opacity:.85;margin-top:6px;">Academy Portal</div>
                    </td>
                </tr>
            </table>
            <div style="margin-top:24px;display:inline-block;background:rgba(255,255,255,.14);padding:10px 16px;border-radius:999px;font-size:13px;font-weight:700;">
                Renovación de nivel
            </div>
            <h1 style="font-size:34px;line-height:1.05;margin:18px 0 0;font-weight:900;">Tu siguiente nivel ya está cerca</h1>
            <p style="font-size:15px;line-height:1.6;margin:14px 0 0;max-width:520px;color:rgba(255,255,255,.9);">
                Queremos que mantengas continuidad en tu proceso académico y no pierdas el ritmo de avance en la plataforma ON English.
            </p>
        </div>

        <div style="background:#ffffff;border:1px solid #d8e1f0;border-top:0;border-radius:0 0 28px 28px;padding:30px 28px 28px;">
            <p style="font-size:16px;line-height:1.7;margin:0 0 18px;">
                {{ $student->full_name }}, tu curso <strong>{{ $course->name }}</strong> finaliza el
                <strong>{{ $course->end_date?->format('d/m/Y') ?? 'N/D' }}</strong>.
            </p>

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0 22px;">
                <tr>
                    <td style="width:50%;padding:0 10px 0 0;vertical-align:top;">
                        <div style="background:#f7faff;border:1px solid #d8e1f0;border-radius:18px;padding:18px;min-height:120px;">
                            <div style="font-size:13px;color:#61749b;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Nivel actual</div>
                            <div style="font-size:30px;line-height:1;font-weight:900;color:#172554;margin:10px 0 8px;">{{ ($currentLevel->sort_order ?? $currentLevel->scale_position) }}/{{ ($currentLevel->program_total ?? $currentLevel->scale_total) }}</div>
                            <div style="font-size:16px;font-weight:800;">{{ $currentLevel->name }}</div>
                            <div style="font-size:13px;color:#61749b;margin-top:6px;">{{ $course->program?->name ?? ($currentLevel->cefr_reference ?: 'Sin referencia CEFR') }}</div>
                        </div>
                    </td>
                    <td style="width:50%;padding:0 0 0 10px;vertical-align:top;">
                        <div style="background:#f7faff;border:1px solid #d8e1f0;border-radius:18px;padding:18px;min-height:120px;">
                            <div style="font-size:13px;color:#61749b;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Siguiente nivel</div>
                            <div style="font-size:30px;line-height:1;font-weight:900;color:#172554;margin:10px 0 8px;">{{ ($nextLevel->sort_order ?? $nextLevel->scale_position) }}/{{ ($nextLevel->program_total ?? $nextLevel->scale_total) }}</div>
                            <div style="font-size:16px;font-weight:800;">{{ $nextLevel->name }}</div>
                            <div style="font-size:13px;color:#61749b;margin-top:6px;">{{ $course->program?->name ?? ($nextLevel->cefr_reference ?: 'Sin referencia CEFR') }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            <div style="background:#fff8e6;border:1px solid #f5d67a;border-radius:18px;padding:18px 20px;margin-bottom:20px;">
                <div style="font-size:14px;font-weight:800;color:#7a5500;margin-bottom:6px;">Acción recomendada</div>
                <div style="font-size:15px;line-height:1.7;color:#694b00;">
                    Gestiona tu inscripción con anticipación para asegurar cupo en <strong>{{ $nextLevel->name }}</strong> y mantener continuidad en tu proceso formativo.
                </div>
            </div>

            <p style="font-size:14px;line-height:1.7;margin:0;color:#61749b;">
                Si necesitas apoyo para continuar con el siguiente nivel, responde este correo o contacta al equipo administrativo de ON English.
            </p>
        </div>
    </div>
</body>
</html>
