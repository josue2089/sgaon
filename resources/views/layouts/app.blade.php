<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ON English | Plataforma Académica</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @include('partials.ui.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    $currentRoute = request()->route()?->getName();
    $user = auth()->user();
    $alertsQuery = \App\Support\CampusScope::apply(
        \App\Models\Alert::query()->where('status', 'open'),
        $user
    );
    $openAlertsCount = (clone $alertsQuery)->count();
    $openAlerts = (clone $alertsQuery)->latest()->take(5)->get();
    $hasRoute = static fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);
@endphp
<div class="fi-shell">
    @include('partials.layout.header')

    <main class="fi-main">
        <div class="fi-container">
            @if(session('success'))
                <div class="flash ok">
                    {{ session('success') }}
                    @if(is_array(session('success_link')))
                        <a href="{{ session('success_link')['url'] }}" style="margin-left:.5rem;font-weight:700;">{{ session('success_link')['label'] }}</a>
                    @endif
                </div>
            @endif
            @if(session('warning'))
                <div class="flash warn">{{ session('warning') }}</div>
            @endif
            @if(session('info'))
                <div class="flash info">{{ session('info') }}</div>
            @endif
            @if($errors->any())
                <div class="flash err"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
<dialog class="ui-modal ui-modal--sm" data-confirm-dialog aria-labelledby="confirm-dialog-title">
    <div class="ui-modal-head">
        <h3 class="section-title" id="confirm-dialog-title" data-confirm-title>Confirmar</h3>
        <button class="ui-modal-x" type="button" data-modal-close aria-label="Cerrar">&times;</button>
    </div>
    <div class="ui-modal-body">
        <p data-confirm-message></p>
        <div data-confirm-reason-wrap hidden>
            <label for="confirm-dialog-reason" data-confirm-reason-label>Motivo</label>
            <textarea id="confirm-dialog-reason" rows="3" data-confirm-reason></textarea>
            <div class="field-error" data-confirm-reason-error hidden>Escribe el motivo para continuar.</div>
        </div>
    </div>
    <div class="form-actions ui-modal-foot">
        <button class="btn secondary" type="button" data-modal-close>Cancelar</button>
        <button class="btn" type="button" data-confirm-ok>Confirmar</button>
    </div>
</dialog>
@if($errors->any())
    <script type="application/json" id="form-errors">@json($errors->getMessages())</script>
@endif
@stack('scripts')
</body>
</html>
