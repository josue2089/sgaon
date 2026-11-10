{{-- Página de error amigable con el contacto de la sede. Sin Vite: tiene que verse aunque falle el resto. #448 --}}
{{-- rescue(): si el error viene de la base, la página igual se muestra sin el contacto. --}}
@php($campus = rescue(fn () => auth()->user()?->campus_id ? \App\Models\Campus::query()->find(auth()->user()->campus_id) : null, null, false))
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · ON English</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #172554; line-height: 1.5; }
        main { max-width: 520px; margin: 10vh auto; padding: 0 16px; }
        .box { background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 4px 18px rgba(23, 37, 84, .08); }
        h1 { font-size: 22px; margin: 0 0 8px; }
        .code { font-size: 13px; color: #64748b; margin: 0 0 16px; }
        a { color: #1d4ed8; }
        .btn { display: inline-block; margin-top: 16px; background: #172554; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: bold; }
        .stack-xs > * + * { margin-top: 4px; }
    </style>
</head>
<body>
<main>
    <div class="box">
        <p class="code">Error @yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <p><strong>¿Necesitas ayuda?</strong></p>
        @include('partials.portal.campus-contact', ['campus' => $campus])
        <a class="btn" href="{{ url('/') }}">Volver al inicio</a>
    </div>
</main>
</body>
</html>
