<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SGA ON English</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    $currentRoute = request()->route()?->getName();
    $user = auth()->user();
    $nav = [
        ['name' => 'Dashboard', 'route' => 'dashboard', 'enabled' => true],
        ['name' => 'Alumnos', 'route' => 'students.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Profesores', 'route' => 'teachers.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Cursos', 'route' => 'courses.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Asistencia', 'route' => 'attendance.index', 'enabled' => in_array($user?->role, ['admin', 'teacher'], true)],
        ['name' => 'Financiero', 'route' => 'finance.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Reportes', 'route' => 'reports.attendance', 'enabled' => $user?->role === 'admin'],
    ];
@endphp
<div class="fi-shell">
    <header class="fi-header">
        <div class="fi-header-inner">
            <div class="fi-brand">
                <span class="fi-brand-mark">ON</span>
                <div>
                    <h1>ON English</h1>
                    <p>Academy Portal</p>
                </div>
            </div>

            <nav class="fi-nav">
                @foreach($nav as $item)
                    @if($item['enabled'])
                        @php($prefix = explode('.', $item['route'])[0])
                        <a class="fi-nav-item {{ str_starts_with((string) $currentRoute, $prefix) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                            {{ $item['name'] }}
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="fi-header-actions">
                <span class="fi-icon-badge">🔔</span>
                <span class="fi-icon-badge">⚙️</span>
                <div class="fi-user-pill">
                    <span class="fi-avatar">{{ strtoupper(substr((string) ($user?->name ?? 'A'), 0, 1)) }}</span>
                    <span>{{ $user?->name ?? 'Admin' }}</span>
                </div>
            </div>
        </div>
    </header>

    <main class="fi-main">
        <div class="fi-container">
            @if(session('success'))
                <div class="flash ok">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="flash err"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @yield('content')

            <form method="POST" action="{{ route('logout') }}" class="fi-logout-wrap">
                @csrf
                <button class="btn secondary" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
