<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ON English | Recuperar contraseña</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login-shell">
    <section class="login-brand">
        <img class="login-logo" src="{{ asset('images/logo.png') }}" alt="ON English Logo">
        <p>Te enviaremos un enlace para restablecer tu contraseña.</p>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <h2>Recuperar contraseña</h2>
            <span class="muted">Ingresa tu correo para continuar.</span>
            @if(session('status'))
                <div class="flash ok">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="flash err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            @endif
            <form class="login-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <div>
                    <label for="email">Correo</label>
                    <input id="email" type="email" name="email" placeholder="tu@email.com" required value="{{ old('email') }}">
                </div>
                <button class="btn" type="submit">Enviar enlace</button>
                <a href="{{ route('login') }}">Volver al inicio de sesión</a>
            </form>
        </div>
    </section>
</div>
</body>
</html>
