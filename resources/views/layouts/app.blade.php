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
    $hasRoute = static fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);
    $nav = [
        ['name' => 'Dashboard', 'route' => 'dashboard', 'enabled' => $hasRoute('dashboard')],
        ['name' => 'Alumnos', 'route' => 'students.index', 'enabled' => $user?->role === 'admin' && $hasRoute('students.index')],
        ['name' => 'Asistencia', 'route' => 'attendance.index', 'enabled' => in_array($user?->role, ['admin', 'teacher'], true) && $hasRoute('attendance.index')],
        ['name' => 'Reportes', 'route' => 'reports.attendance', 'enabled' => $user?->role === 'admin' && $hasRoute('reports.attendance')],
    ];
    $extraNav = [
        ['name' => 'Profesores', 'route' => 'teachers.index', 'enabled' => $user?->role === 'admin' && $hasRoute('teachers.index')],
        ['name' => 'Cursos', 'route' => 'courses.index', 'enabled' => $user?->role === 'admin' && $hasRoute('courses.index')],
        ['name' => 'Financiero', 'route' => 'finance.index', 'enabled' => $user?->role === 'admin' && $hasRoute('finance.index')],
        ['name' => 'Inscripciones', 'route' => 'enrollments.index', 'enabled' => $user?->role === 'admin' && $hasRoute('enrollments.index')],
        ['name' => 'Recuperativas', 'route' => 'makeups.index', 'enabled' => $user?->role === 'admin' && $hasRoute('makeups.index')],
    ];
    $configNav = [
        ['name' => 'Campus', 'route' => 'campuses.index', 'enabled' => $user?->isMasterAdmin() && $hasRoute('campuses.index')],
        ['name' => 'Períodos', 'route' => 'periods.index', 'enabled' => $user?->isMasterAdmin() && $hasRoute('periods.index')],
        ['name' => 'Horarios', 'route' => 'schedules.index', 'enabled' => $user?->isMasterAdmin() && $hasRoute('schedules.index')],
        ['name' => 'Feriados', 'route' => 'holidays.index', 'enabled' => $user?->isMasterAdmin() && $hasRoute('holidays.index')],
        ['name' => 'Programas', 'route' => 'programs.index', 'enabled' => $user?->isMasterAdmin() && $hasRoute('programs.index')],
    ];
    $extraPrefixes = ['teachers', 'courses', 'finance', 'enrollments', 'makeups'];
    $extraActive = in_array(explode('.', (string) $currentRoute)[0], $extraPrefixes, true);
    $configPrefixes = ['campuses', 'periods', 'schedules', 'holidays', 'programs', 'program-levels', 'program-level-lessons'];
    $configActive = in_array(explode('.', (string) $currentRoute)[0], $configPrefixes, true);
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
                        @php
                            $prefix = explode('.', $item['route'])[0];
                        @endphp
                        <a class="fi-nav-item {{ str_starts_with((string) $currentRoute, $prefix) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                            <span class="fi-nav-icon" aria-hidden="true">
                                @switch($item['name'])
                                    @case('Dashboard')
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M4 4h7v7H4zM13 4h7v5h-7zM13 11h7v9h-7zM4 13h7v7H4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                                        @break
                                    @case('Alumnos')
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M16 19a4 4 0 0 0-8 0M15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM20 18a3 3 0 0 0-3-3M4 18a3 3 0 0 1 3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                        @break
                                    @case('Profesores')
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M3 8l9-4 9 4-9 4-9-4Zm3 3.5V16c0 2.2 2.7 4 6 4s6-1.8 6-4v-4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        @break
                                    @case('Cursos')
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14H6.5A2.5 2.5 0 0 0 4 20V6.5ZM4 20V8a2 2 0 0 1 2-2h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        @break
                                    @case('Asistencia')
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M8 4v3M16 4v3M4 10h16M7 14l2 2 5-5M6 6h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        @break
                                    @case('Financiero')
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5v-9ZM3 10h18" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                                        @break
                                    @case('Reportes')
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M5 19V9M12 19V5M19 19v-7M4 19h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                        @break
                                @endswitch
                            </span>
                            {{ $item['name'] }}
                        </a>
                    @endif
                @endforeach
                @if($user?->role === 'admin')
                    <details class="fi-menu fi-menu-inline">
                        <summary class="fi-nav-item {{ $extraActive ? 'active' : '' }}">
                            <span class="fi-nav-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M5 5h5v5H5zM14 5h5v5h-5zM5 14h5v5H5zM14 14h5v5h-5z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                            </span>
                            Más
                        </summary>
                        <div class="fi-menu-panel fi-menu-panel-nav">
                            @foreach($extraNav as $item)
                                @if($item['enabled'])
                                    <a href="{{ route($item['route']) }}" class="fi-menu-link">{{ $item['name'] }}</a>
                                @endif
                            @endforeach
                        </div>
                    </details>
                @endif
                @if($user?->isMasterAdmin())
                    <details class="fi-menu fi-menu-inline">
                        <summary class="fi-nav-item {{ $configActive ? 'active' : '' }}">
                            <span class="fi-nav-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l2 2.2 2.9-.2.7 2.8 2.5 1.4-1.4 2.5 1.4 2.5-2.5 1.4-.7 2.8-2.9-.2L12 21l-2.2-2.2-2.9.2-.7-2.8-2.5-1.4 1.4-2.5-1.4-2.5 2.5-1.4.7-2.8 2.9.2L12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
                            </span>
                            Configuración
                        </summary>
                        <div class="fi-menu-panel fi-menu-panel-nav">
                            @foreach($configNav as $item)
                                @if($item['enabled'])
                                    <a href="{{ route($item['route']) }}" class="fi-menu-link">{{ $item['name'] }}</a>
                                @endif
                            @endforeach
                        </div>
                    </details>
                @endif
            </nav>

            <div class="fi-header-actions">
                <details class="fi-menu">
                    <summary class="fi-icon-badge">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M15 17H5.8a.8.8 0 0 1-.65-1.27c1.06-1.45 1.85-3 1.85-5.18a5 5 0 0 1 10 0c0 2.17.8 3.73 1.85 5.18A.8.8 0 0 1 18.2 17H15Zm0 0a3 3 0 0 1-6 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        @if($openAlertsCount > 0)
                            <span class="fi-icon-dot"></span>
                        @endif
                    </summary>
                    <div class="fi-menu-panel">
                        <div class="fi-menu-title">Notificaciones</div>
                        @forelse($openAlerts as $alert)
                            @php
                                $alertUrl = route('dashboard');
                                if ($alert->type === 'finance' && $user?->role === 'admin' && $alert->student_id) {
                                    $alertUrl = route('finance.index', ['student_id' => $alert->student_id]);
                                } elseif ($alert->type === 'attendance') {
                                    $alertUrl = ($user?->role === 'admin' && $alert->student_id)
                                        ? route('students.show', $alert->student_id)
                                        : route('attendance.index');
                                } elseif ($alert->type === 'level_renewal' && $user?->role === 'admin' && $alert->student_id) {
                                    $alertUrl = route('students.show', $alert->student_id);
                                } elseif ($alert->type === 'makeup_recovery') {
                                    $alertUrl = $user?->role === 'admin'
                                        ? ($hasRoute('makeups.index') ? route('makeups.index', ['student_id' => $alert->student_id]) : route('dashboard'))
                                        : route('portal.student');
                                } else {
                                    $alertUrl = route('dashboard');
                                }
                            @endphp
                            <a href="{{ $alertUrl }}" class="fi-menu-link">
                                <strong>{{ ucfirst($alert->type) }}</strong>
                                <small>{{ \Illuminate\Support\Str::limit($alert->message, 52) }}</small>
                            </a>
                        @empty
                            <div class="fi-menu-empty">Sin alertas abiertas</div>
                        @endforelse
                        @if($hasRoute('reports.attendance'))<a href="{{ route('reports.attendance') }}" class="fi-menu-footer">Ver reportes</a>@endif
                    </div>
                </details>

                <details class="fi-menu">
                    <summary class="fi-icon-badge">
                        <svg viewBox="0 0 24 24" fill="none"><path d="m12 8.5 1.1-2.8 2.1 1 2.4-1.3 1.3 2.4-1 2.1 1.8 1.6-1.8 1.6 1 2.1-1.3 2.4-2.4-1.3-2.1 1L12 15.5l-1.1 2.8-2.1-1-2.4 1.3-1.3-2.4 1-2.1L4.3 12l1.8-1.6-1-2.1 1.3-2.4 2.4 1.3 2.1-1L12 8.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.3" stroke="currentColor" stroke-width="1.7"/></svg>
                    </summary>
                    <div class="fi-menu-panel">
                        <div class="fi-menu-title">Configuración</div>
                        <a href="{{ route('dashboard') }}" class="fi-menu-link">Panel principal</a>
                        @if($user?->role === 'admin')
                            @if($hasRoute('reports.audit'))<a href="{{ route('reports.audit') }}" class="fi-menu-link">Auditoría</a>@endif
                            @if($hasRoute('reports.payments'))<a href="{{ route('reports.payments') }}" class="fi-menu-link">Reporte financiero</a>@endif
                            @if($hasRoute('reports.level-renewals'))<a href="{{ route('reports.level-renewals') }}" class="fi-menu-link">Renovación de niveles</a>@endif
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
                        <div class="fi-menu-empty">Rol: {{ strtoupper((string) $user?->role) }}{{ $user?->isMasterAdmin() ? ' · MASTER' : '' }}</div>
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
@stack('scripts')
</body>
</html>
