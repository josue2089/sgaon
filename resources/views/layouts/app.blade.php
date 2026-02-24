<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ON English | Plataforma Académica</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    $currentRoute = request()->route()?->getName();
    $user = auth()->user();
    $alertsQuery = \App\Models\Alert::query()->where('status', 'open');
    if ($user?->campus_id) {
        $alertsQuery->where('campus_id', $user->campus_id);
    }
    $openAlertsCount = (clone $alertsQuery)->count();
    $openAlerts = (clone $alertsQuery)->latest()->take(5)->get();
    $nav = [
        ['name' => 'Dashboard', 'route' => 'dashboard', 'enabled' => true],
        ['name' => 'Alumnos', 'route' => 'students.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Profesores', 'route' => 'teachers.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Cursos', 'route' => 'courses.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Grupos', 'route' => 'groups.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Inscripciones', 'route' => 'enrollments.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Sesiones', 'route' => 'sessions.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Asistencia', 'route' => 'attendance.index', 'enabled' => in_array($user?->role, ['admin', 'teacher'], true)],
        ['name' => 'Financiero', 'route' => 'finance.index', 'enabled' => $user?->role === 'admin'],
        ['name' => 'Reportes', 'route' => 'reports.attendance', 'enabled' => $user?->role === 'admin'],
    ];
@endphp
<div class="fi-shell">
    <header class="fi-header">
        <div class="fi-header-inner">
            <div class="fi-brand">
                <span class="fi-brand-mark">
                    <img src="{{ asset('images/logo.png') }}" alt="ON English Logo">
                </span>
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
                <details class="fi-menu">
                    <summary class="fi-icon-badge">
                        🔔
                        @if($openAlertsCount > 0)
                            <span class="fi-icon-dot"></span>
                        @endif
                    </summary>
                    <div class="fi-menu-panel">
                        <div class="fi-menu-title">Notificaciones</div>
                        @forelse($openAlerts as $alert)
                            <a href="{{ route('dashboard') }}" class="fi-menu-link">
                                <strong>{{ ucfirst($alert->type) }}</strong>
                                <small>{{ \Illuminate\Support\Str::limit($alert->message, 52) }}</small>
                            </a>
                        @empty
                            <div class="fi-menu-empty">Sin alertas abiertas</div>
                        @endforelse
                        <a href="{{ route('reports.attendance') }}" class="fi-menu-footer">Ver reportes</a>
                    </div>
                </details>

                <details class="fi-menu">
                    <summary class="fi-icon-badge">⚙️</summary>
                    <div class="fi-menu-panel">
                        <div class="fi-menu-title">Configuración</div>
                        <a href="{{ route('dashboard') }}" class="fi-menu-link">Panel principal</a>
                        @if($user?->role === 'admin')
                            <a href="{{ route('reports.audit') }}" class="fi-menu-link">Auditoría</a>
                            <a href="{{ route('reports.payments') }}" class="fi-menu-link">Reporte financiero</a>
                        @endif
                    </div>
                </details>

                <details class="fi-menu">
                    <summary class="fi-user-pill">
                        <span class="fi-avatar">{{ strtoupper(substr((string) ($user?->name ?? 'A'), 0, 1)) }}</span>
                        <span>{{ $user?->name ?? 'Admin' }}</span>
                    </summary>
                    <div class="fi-menu-panel fi-menu-panel-profile">
                        <div class="fi-menu-title">{{ $user?->name ?? 'Usuario' }}</div>
                        <div class="fi-menu-empty">Rol: {{ strtoupper((string) $user?->role) }}</div>
                        <a href="{{ route('dashboard') }}" class="fi-menu-link">Ir al dashboard</a>
                        @if($user?->role === 'student')
                            <a href="{{ route('portal.student') }}" class="fi-menu-link">Mi portal</a>
                        @endif
                        @if($user?->role === 'representative')
                            <a href="{{ route('portal.representative') }}" class="fi-menu-link">Portal familia</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="fi-menu-logout" type="submit">Cerrar sesión</button>
                        </form>
                    </div>
                </details>
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
        </div>
    </main>
</div>
</body>
</html>
