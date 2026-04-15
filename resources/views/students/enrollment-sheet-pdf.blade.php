<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ficha de inscripción {{ $student->full_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #172554; margin: 26px; }
        .header { border-bottom: 2px solid #d8e1f0; padding-bottom: 12px; margin-bottom: 18px; }
        .brand { width: 100%; }
        .brand td { vertical-align: middle; }
        .brand-logo img { width: 72px; height: auto; }
        .brand-meta { text-align: right; }
        h1 { margin: 0; font-size: 21px; }
        h2 { margin: 18px 0 8px; font-size: 14px; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 10px; }
        .grid td { width: 50%; vertical-align: top; }
        .card { border: 1px solid #d8e1f0; border-radius: 10px; padding: 12px; }
        .row { margin-bottom: 6px; }
        .label { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d8e1f0; padding: 8px 6px; text-align: left; vertical-align: top; }
        th { background: #eef4ff; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    @include('students.partials.enrollment-sheet-content')
</body>
</html>
