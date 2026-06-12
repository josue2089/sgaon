<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acceso administrativo</title>
</head>
<body style="font-family: Arial, sans-serif; color: #172554; line-height: 1.5;">
    <h1 style="font-size: 20px;">Bienvenido a ON English Academy Portal</h1>
    <p>Hola {{ $user->name }},</p>
    <p>Se creó tu cuenta de administrador. Usa estos datos para ingresar:</p>
    <ul>
        <li><strong>URL:</strong> <a href="{{ route('login') }}">{{ route('login') }}</a></li>
        <li><strong>Email:</strong> {{ $user->email }}</li>
        <li><strong>Contraseña temporal:</strong> {{ $plainPassword }}</li>
    </ul>
    <p>Por seguridad, cambia tu contraseña después del primer acceso desde la opción de recuperación si lo necesitas.</p>
    <p>ON English Academy Portal</p>
</body>
</html>
