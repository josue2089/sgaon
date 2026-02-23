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
    $todayLabel = now()->locale('es')->isoFormat('dddd, D [de] MMMM YYYY');
@endphp
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="brand">
            <span class="brand-mark">ON</span>
            <h1>SGA ON English</h1>
            <p>Gestión académica de alto impacto.</p>
        </div>
        <nav class="nav-list">
            <a class="nav-link {{ $currentRoute === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        @if(auth()->user()?->role === 'admin')
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'students.') ? 'active' : '' }}" href="{{ route('students.index') }}">Alumnos</a>
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'teachers.') ? 'active' : '' }}" href="{{ route('teachers.index') }}">Profesores</a>
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'courses.') ? 'active' : '' }}" href="{{ route('courses.index') }}">Cursos</a>
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'groups.') ? 'active' : '' }}" href="{{ route('groups.index') }}">Grupos</a>
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'enrollments.') ? 'active' : '' }}" href="{{ route('enrollments.index') }}">Inscripciones</a>
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'sessions.') ? 'active' : '' }}" href="{{ route('sessions.index') }}">Sesiones</a>
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'finance.') ? 'active' : '' }}" href="{{ route('finance.index') }}">Financiero</a>
            <a class="nav-link {{ $currentRoute === 'reports.attendance' ? 'active' : '' }}" href="{{ route('reports.attendance') }}">Rep. Asistencia</a>
            <a class="nav-link {{ $currentRoute === 'reports.payments' ? 'active' : '' }}" href="{{ route('reports.payments') }}">Rep. Pagos</a>
            <a class="nav-link {{ $currentRoute === 'reports.audit' ? 'active' : '' }}" href="{{ route('reports.audit') }}">Rep. Auditoría</a>
        @endif
        @if(in_array(auth()->user()?->role, ['admin', 'teacher'], true))
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'attendance.') ? 'active' : '' }}" href="{{ route('attendance.index') }}">Asistencia</a>
        @endif
        @if(auth()->user()?->role === 'student')
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'portal.student') ? 'active' : '' }}" href="{{ route('portal.student') }}">Mi Portal</a>
        @endif
        @if(auth()->user()?->role === 'representative')
            <a class="nav-link {{ str_starts_with((string) $currentRoute, 'portal.representative') ? 'active' : '' }}" href="{{ route('portal.representative') }}">Portal Familia</a>
        @endif
        </nav>
        <div class="sidebar-actions">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn secondary" type="submit">Salir</button>
            </form>
        </div>
    </aside>

    <main class="app-main">
        <header class="topbar">
            <div>
                <h2>Centro Académico</h2>
                <p>{{ ucfirst($todayLabel) }}</p>
            </div>
            <div class="topbar-pill">
                <span class="dot" aria-hidden="true"></span>
                {{ $user?->name ?? 'Usuario' }} ({{ strtoupper((string) $user?->role) }})
            </div>
        </header>
        <div class="page-content">
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
