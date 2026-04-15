<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ON English | Restablecer contraseña</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login-shell">
    <section class="login-brand">
        <img class="login-logo" src="{{ asset('images/logo.png') }}" alt="ON English Logo">
        <p>Define una nueva contraseña para tu cuenta.</p>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <h2>Nueva contraseña</h2>
            <span class="muted">Completa los datos para finalizar.</span>
            @if($errors->any())
                <div class="flash err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            @endif
            <form class="login-form" method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label for="email">Correo</label>
                    <input id="email" type="email" name="email" placeholder="tu@email.com" required value="{{ old('email', $email) }}">
                </div>
                <div>
                    <label for="password">Nueva contraseña</label>
                    <input id="password" type="password" name="password" required>
                </div>
                <div>
                    <label for="password_confirmation">Confirmar contraseña</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required>
                </div>
                <button class="btn" type="submit">Guardar contraseña</button>
                <a href="{{ route('login') }}">Volver al inicio de sesión</a>
            </form>
        </div>
    </section>
</div>
</body>
</html>
