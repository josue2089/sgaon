<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>ON English | Activar acceso</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @include('partials.ui.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login-shell">
    <section class="login-brand">
        <img class="login-logo" src="{{ asset('images/logo.png') }}" alt="ON English Logo">
        <p>Elige tu contraseña para entrar al portal.</p>
    </section>

    <section class="login-panel">
        <div class="login-card">
            @if($invitation)
                <h2>Hola, {{ $invitation->user->name }}</h2>
                <span class="muted">Tu usuario para iniciar sesión es <strong>{{ $loginHint }}</strong>.</span>
                @if($errors->any())
                    <div class="flash err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
                @endif
                <form class="login-form" method="POST" action="{{ route('portal.activate.store', $token) }}" data-guard-submit>
                    @csrf
                    <div>
                        <label for="password">Contraseña</label>
                        <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required>
                    </div>
                    <div>
                        <label for="password_confirmation">Confirmar contraseña</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                    </div>
                    <button class="btn" type="submit">Guardar contraseña</button>
                </form>
            @else
                <h2>Este enlace ya no sirve</h2>
                <span class="muted">Venció o ya se usó. Si ya elegiste tu contraseña, inicia sesión. Si no, pide uno nuevo en la sede o con "¿Olvidaste tu contraseña?".</span>
                <div class="login-form">
                    <a class="btn" href="{{ route('login') }}">Ir a iniciar sesión</a>
                </div>
            @endif
        </div>
    </section>
</div>
</body>
</html>
