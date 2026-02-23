<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SGA ON English</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f7fb; color: #1f2937; }
        .wrap { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
        aside { background: #0f3d7a; color: #fff; padding: 20px; }
        aside a { color: #fff; text-decoration: none; display: block; padding: 8px 0; }
        main { padding: 24px; }
        .card { background: #fff; border-radius: 10px; padding: 16px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .row { display: flex; gap: 12px; flex-wrap: wrap; }
        .kpi { flex: 1; min-width: 160px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; font-size: 14px; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn { background: #0f3d7a; color: white; border: 0; padding: 8px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn.secondary { background: #64748b; }
        .muted { color: #6b7280; }
        .flash { padding: 10px; border-radius: 6px; margin-bottom: 12px; }
        .ok { background: #dcfce7; color: #166534; }
        .err { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="wrap">
    <aside>
        <h2>SGA ON</h2>
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @if(auth()->user()?->role === 'admin')
            <a href="{{ route('students.index') }}">Alumnos</a>
            <a href="{{ route('teachers.index') }}">Profesores</a>
            <a href="{{ route('courses.index') }}">Cursos</a>
            <a href="{{ route('groups.index') }}">Grupos</a>
            <a href="{{ route('enrollments.index') }}">Inscripciones</a>
            <a href="{{ route('sessions.index') }}">Sesiones</a>
            <a href="{{ route('finance.index') }}">Financiero</a>
            <a href="{{ route('reports.attendance') }}">Rep. Asistencia</a>
            <a href="{{ route('reports.payments') }}">Rep. Pagos</a>
            <a href="{{ route('reports.audit') }}">Rep. Auditoría</a>
        @endif
        @if(in_array(auth()->user()?->role, ['admin', 'teacher'], true))
            <a href="{{ route('attendance.index') }}">Asistencia</a>
        @endif
        @if(auth()->user()?->role === 'student')
            <a href="{{ route('portal.student') }}">Mi Portal</a>
        @endif
        @if(auth()->user()?->role === 'representative')
            <a href="{{ route('portal.representative') }}">Portal Familia</a>
        @endif
        <form method="POST" action="{{ route('logout') }}" style="margin-top:16px;">
            @csrf
            <button class="btn secondary" type="submit">Salir</button>
        </form>
    </aside>
    <main>
        @if(session('success'))
            <div class="flash ok">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="flash err"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
