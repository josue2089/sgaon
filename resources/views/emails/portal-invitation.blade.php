@php($isRecovery = $purpose === \App\Models\PortalInvitation::PURPOSE_RECOVERY)
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $isRecovery ? 'Recupera tu acceso' : 'Activa tu acceso' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #172554; line-height: 1.5;">
    <h1 style="font-size: 20px;">{{ $isRecovery ? 'Recupera tu acceso a ON English' : 'Bienvenido al portal de ON English' }}</h1>
    @if($sentToRepresentative)
        <p>Hola, te escribimos como representante de <strong>{{ $user->name }}</strong>.</p>
        <p>{{ $isRecovery ? 'Se pidió recuperar el acceso de su cuenta.' : 'Ya puede tener su propia cuenta en el portal.' }} Con este enlace se elige la contraseña:</p>
    @else
        <p>Hola {{ $user->name }},</p>
        <p>{{ $isRecovery ? 'Recibimos una solicitud para recuperar tu acceso.' : 'Ya puedes entrar al portal para ver la información académica y los pagos.' }} Con este enlace eliges tu contraseña:</p>
    @endif
    <p style="margin: 24px 0;">
        <a href="{{ $url }}" style="background:#172554;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;">{{ $isRecovery ? 'Elegir nueva contraseña' : 'Activar mi acceso' }}</a>
    </p>
    <p><strong>Usuario para iniciar sesión:</strong> {{ $loginHint }}</p>
    <p>{{ $isRecovery ? 'Este enlace vence en 1 hora.' : 'Este enlace vence en 7 días.' }} Si no esperabas este correo, puedes ignorarlo.</p>
    <p>ON English Academy</p>
</body>
</html>
