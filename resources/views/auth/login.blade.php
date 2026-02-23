<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ON English | Iniciar Sesión</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login-shell">
    <section class="login-brand">
        <span class="login-badge">
            <img src="{{ asset('images/logo.png') }}" alt="ON English Logo">
        </span>
        <h1>ON English Academy</h1>
        <p>Portal de gestión académica para seguimiento de alumnos, asistencia y finanzas en una sola plataforma.</p>
        <img src="{{ asset('images/on-brand-login.png') }}" alt="ON English visual" style="max-width:340px;width:100%;border-radius:20px;border:3px solid rgba(255,255,255,.2);box-shadow:0 18px 32px rgba(0,0,0,.3);">
    </section>

    <section class="login-panel">
        <div class="login-card">
            <h2>Bienvenido</h2>
            <span class="muted">Inicia sesión para continuar.</span>
            @if($errors->any())
                <div class="flash err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            @endif
            <form class="login-form" method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div>
                    <label for="email">Correo</label>
                    <input id="email" type="email" name="email" placeholder="tu@email.com" required value="{{ old('email') }}">
                </div>
                <div>
                    <label for="password">Contraseña</label>
                    <input id="password" type="password" name="password" placeholder="••••••••" required>
                </div>
                <button class="btn" type="submit">Entrar</button>
            </form>
        </div>
    </section>
</div>
</body>
</html>
