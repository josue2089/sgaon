<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Bitácora {{ $student?->full_name }}</title>
    {{-- Bitácora File A/B con el formato de la plantilla del cliente. #470 --}}
    <style>
        @page { margin: 22px 26px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #9ca3af; padding: 3px 5px; vertical-align: middle; }
        th { background: #e5e7eb; font-size: 8px; text-transform: uppercase; }
        .layout > tbody > tr > td { border: 0; padding: 0; vertical-align: top; }
        .title { font-size: 15px; font-weight: 700; text-align: center; text-transform: uppercase; }
        .label { background: #f3f4f6; font-weight: 700; width: 22%; font-size: 8px; text-transform: uppercase; }
        .logo { max-height: 34px; }
        .center { text-align: center; }
        .sign { width: 17%; }
        .absent { color: #be123c; font-weight: 700; }
        .muted { color: #6b7280; }
        .gap { height: 8px; }
    </style>
</head>
<body>
<table class="layout">
    <tr>
        <td style="width:56%; padding-right:10px;">
            <table style="border:0;"><tr>
                <td style="border:0; width:25%;">@if(!empty($logoDataUri))<img class="logo" src="{{ $logoDataUri }}" alt="ON English">@endif</td>
                <td style="border:0;" class="title">{{ $title }}</td>
            </tr></table>
            <div class="gap"></div>
            <table>
                <tr><td class="label">Level</td><td>{{ $levelName }}</td><td class="label">Teacher</td><td>{{ $teacher ?? '' }}</td></tr>
                <tr><td class="label">Starting date</td><td>{{ $startDate?->format('d/m/Y') }}</td><td class="label">Finishing date</td><td>{{ $endDate?->format('d/m/Y') }}</td></tr>
                <tr><td class="label">Schedule</td><td colspan="3">{{ $schedule ?? '' }}</td></tr>
            </table>
            <div class="gap"></div>
            <table>
                <tr><td class="label">Student</td><td>{{ $student?->full_name }}</td><td class="label">ID N°</td><td>{{ $student?->document_id }}</td></tr>
                <tr><td class="label">Representative</td><td>{{ $representative?->full_name }}</td><td class="label">#</td><td>{{ $representativePhone }}</td></tr>
            </table>
        </td>
        <td>
            <table>
                <thead><tr><th style="width:8%;">#</th><th>Content</th><th style="width:20%;">Date</th><th style="width:26%;">Teacher</th></tr></thead>
                <tbody>
                @for($i = 0; $i < max(\App\Support\StudentLogbook::EXTRA_ROWS, $makeups->count()); $i++)
                    @php($row = $makeups->get($i))
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td>{{ $row['content'] ?? '' }}</td>
                        <td>{{ $row ? ($row['date']?->format('d/m/Y') ?? '') : '' }}</td>
                        <td>{{ $row['teacher'] ?? '' }}</td>
                    </tr>
                @endfor
                </tbody>
            </table>
        </td>
    </tr>
</table>

<div class="gap"></div>
<table>
    <thead>
    <tr>
        <th style="width:9%;">Date</th>
        <th>Unit</th>
        <th style="width:5%;">Class #</th>
        <th style="width:10%;">Attendance</th>
        <th class="sign">Student's / Parent's signature</th>
        <th class="sign">Teacher's signature</th>
    </tr>
    </thead>
    <tbody>
    @forelse($sessions as $session)
        <tr>
            <td class="center">{{ $session['date']?->format('d/m/Y') }}</td>
            <td>
                <strong>{{ $session['unit'] }}</strong>
                @if($session['activity'])<span class="muted"> · {{ $session['activity'] }}</span>@endif
                @if($session['topic'])<div class="muted">Visto: {{ $session['topic'] }}</div>@endif
            </td>
            <td class="center">{{ $session['number'] }}</td>
            <td class="center {{ in_array($session['attendance_status'], ['absent', 'justified'], true) ? 'absent' : '' }}">{{ $session['attendance'] ?? '' }}</td>
            <td></td>
            <td></td>
        </tr>
    @empty
        <tr><td colspan="6" class="center muted">El curso todavía no tiene clases planificadas.</td></tr>
    @endforelse
    </tbody>
</table>
<p class="muted">Asistencias: {{ $summary['present'] }} · Inasistencias: {{ $summary['absent'] }} · Clases del curso: {{ $summary['total'] }}@if($campusName) · Sede {{ $campusName }}@endif · Generado el {{ now()->format('d/m/Y') }}</p>
</body>
</html>
