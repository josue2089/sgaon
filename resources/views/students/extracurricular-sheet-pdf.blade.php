<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Planilla extracurricular {{ $student->full_name }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; margin: 0; }
        .band { background: #97c93d; padding: 18px 32px; }
        .band table { width: 100%; border-collapse: collapse; }
        .band td { vertical-align: middle; border: 0; padding: 0; }
        .band img { max-height: 64px; max-width: 120px; }
        .logos td { padding-right: 14px; }
        .pill { display: inline-block; background: #fff; color: #1f2937; border-radius: 12px; padding: 3px 12px; font-weight: 700; font-size: 11px; }
        .band-title { color: #fff; font-size: 22px; font-weight: 700; line-height: 1.1; margin-top: 6px; }
        .content { padding: 18px 32px 10px; }
        .section { background: #d6387a; color: #fff; font-weight: 700; padding: 5px 12px; border-radius: 6px; display: inline-block; margin: 12px 0 6px; font-size: 11px; }
        .box { border: 1.5px solid #97c93d; border-radius: 8px; padding: 6px; }
        .fields { width: 100%; border-collapse: separate; border-spacing: 4px; }
        .fields td { padding: 0; vertical-align: top; }
        .label { background: #97c93d; color: #fff; font-weight: 700; padding: 5px 8px; border-radius: 5px; white-space: nowrap; }
        .value { border: 1.5px solid #97c93d; border-radius: 5px; padding: 4px 8px; min-height: 14px; }
        .value.tall { height: 34px; }
        .months { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .months th { background: #d6387a; color: #fff; padding: 5px; font-size: 10px; border: 1px solid #c4c4c4; }
        .months td { border: 1px solid #c4c4c4; padding: 5px; }
        .months td.month { background: #97c93d; color: #fff; font-weight: 700; text-align: center; width: 18%; }
        .status-paid { color: #15803d; font-weight: 700; }
        .status-overdue { color: #be123c; font-weight: 700; }
        .terms { margin: 0; padding-left: 18px; }
        .terms li { margin-bottom: 5px; }
        .muted { color: #6b7280; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
@php
    $rep = $representative;
    $statusClass = fn (string $status) => match ($status) {
        'Pagado' => 'status-paid',
        'Vencido' => 'status-overdue',
        default => '',
    };
@endphp

@foreach([1, 2] as $page)
    @if($page === 2)<div class="page-break"></div>@endif
    <div class="band">
        <table>
            <tr>
                <td style="width:45%;">
                    <table class="logos"><tr>
                        @if($logoDataUri)<td><img src="{{ $logoDataUri }}" alt="ON English"></td>@endif
                        @if($campusLogo)<td><img src="{{ $campusLogo }}" alt="{{ $campusName }}"></td>@endif
                    </tr></table>
                </td>
                <td>
                    <span class="pill">Planilla de inscripción</span>
                    <div class="band-title">ACTIVIDADES<br>EXTRACURRICULARES</div>
                    @if($campusName)<div style="color:#fff;margin-top:4px;">{{ $campusName }}</div>@endif
                </td>
            </tr>
        </table>
    </div>

    <div class="content">
    @if($page === 1)
        <div class="section">Datos del alumno / alumna</div>
        <div class="box">
            <table class="fields">
                <tr><td class="label" style="width:22%;">Nombres y apellidos:</td><td class="value" colspan="5">{{ $student->full_name }}</td></tr>
                <tr>
                    <td class="label">Grado / Año:</td><td class="value">{{ $student->school_grade }}</td>
                    <td class="label">Sección:</td><td class="value">{{ $student->school_section }}</td>
                    <td class="label">Edad:</td><td class="value">{{ $student->age }}</td>
                </tr>
                <tr><td class="label">Fecha de nacimiento:</td><td class="value" colspan="5">{{ $student->birth_date?->format('d/m/Y') }}</td></tr>
                <tr><td class="label" colspan="6">Alergias o condiciones médicas especiales:</td></tr>
                <tr><td class="value tall" colspan="6">{{ $student->medical_has_allergies ? ($student->medical_allergy_details ?: 'Sí') : '' }}{{ $student->medical_notes ? ' · '.$student->medical_notes : '' }}</td></tr>
            </table>
        </div>

        <div class="section">Datos del padre, madre o representante legal</div>
        <div class="box">
            <table class="fields">
                <tr>
                    <td class="label" style="width:22%;">Nombres y apellidos:</td><td class="value" colspan="3">{{ $rep?->full_name }}</td>
                    <td class="label">C.I.:</td><td class="value">{{ $rep?->document_id }}</td>
                </tr>
                <tr>
                    <td class="label">Nacionalidad V / E:</td><td class="value">{{ $rep?->nationality }}</td>
                    <td class="label">Parentesco con el alumno:</td><td class="value" colspan="3">{{ $rep?->relation }}</td>
                </tr>
                <tr>
                    <td class="label">Teléfono celular:</td><td class="value">{{ $rep?->mobile_phone ?: $rep?->phone }}</td>
                    <td class="label">Correo:</td><td class="value" colspan="3">{{ $rep?->email }}</td>
                </tr>
                <tr><td class="label">Dirección de habitación:</td><td class="value" colspan="5">{{ $rep?->address }}</td></tr>
                <tr><td class="label">Persona autorizada para retirarlo(a):</td><td class="value" colspan="5">{{ $authorizedContact ? trim($authorizedContact->full_name.($authorizedContact->relationship ? ' ('.$authorizedContact->relationship.')' : '').($authorizedContact->mobile_phone ? ' · '.$authorizedContact->mobile_phone : '')) : '' }}</td></tr>
                <tr><td class="label">Teléfono de emergencia:</td><td class="value" colspan="5">{{ $student->emergency_phone }}</td></tr>
            </table>
        </div>

        <div class="section">Detalles de la actividad extracurricular</div>
        <div class="box">
            <table class="fields">
                <tr>
                    <td class="label" style="width:22%;">Actividad seleccionada:</td><td class="value">{{ $activity }}</td>
                    <td class="label">Horario elegido:</td><td class="value">{{ $schedule }}</td>
                </tr>
                <tr><td class="label">Profesor(a) asignado(a):</td><td class="value" colspan="3">{{ $teacher }}</td></tr>
                <tr>
                    <td class="label">Fecha de inicio:</td><td class="value">{{ $course->start_date?->format('d/m/Y') }}</td>
                    <td class="label">Fecha de finalización:</td><td class="value">{{ $course->end_date?->format('d/m/Y') }}</td>
                </tr>
            </table>
        </div>

        <div class="section">Sección académica</div>
        <div class="box">
            <table class="fields">
                <tr><td class="label" style="width:22%;">Nivel / Dominio actual:</td><td class="value">{{ $student->extracurricular_level }}</td></tr>
                <tr><td class="label" colspan="2">Objetivos / Competencias a desarrollar:</td></tr>
                <tr><td class="value tall" colspan="2">{{ $student->extracurricular_objectives }}</td></tr>
                <tr><td class="label" colspan="2">Observaciones / Recomendaciones del docente:</td></tr>
                <tr><td class="value tall" colspan="2">{{ $student->teacher_observations }}</td></tr>
            </table>
        </div>
    @else
        <div class="section">Sección administrativa (control de pagos)</div>
        <table class="fields">
            <tr><td class="label" style="width:30%;">Condición de pago / Matrícula:</td><td class="value">{{ $student->payment_condition }}</td></tr>
        </table>

        <div class="section">Control de mensualidades</div>
        <table class="months">
            <thead><tr><th>Mes</th><th>Estatus</th><th>Monto</th><th>Fecha de pago</th><th>Referencia / Recibo N°</th><th>Firma / Sello admin.</th></tr></thead>
            <tbody>
            @forelse($months as $row)
                <tr>
                    <td class="month">{{ $row['label'] }}</td>
                    <td class="{{ $statusClass($row['status']) }}">{{ $row['status'] }}</td>
                    <td>{{ $row['amount'] }}</td>
                    <td>{{ $row['paid_at'] }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">El curso no tiene fecha de inicio y de fin configuradas.</td></tr>
            @endforelse
            </tbody>
        </table>

        <div class="section">Términos y condiciones</div>
        <div class="box">
            <ol class="terms">
                @foreach($terms as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ol>
        </div>
        <table class="fields" style="margin-top:14px;">
            <tr><td class="label" style="width:34%;">Firma del padre, madre o representante:</td><td class="value tall" colspan="3"></td></tr>
            <tr>
                <td class="label">C.I.:</td><td class="value">{{ $rep?->nationality ? $rep->nationality.'-' : '' }}{{ $rep?->document_id }}</td>
                <td class="label" style="width:12%;">Fecha:</td><td class="value">{{ now()->format('d/m/Y') }}</td>
            </tr>
        </table>
        <p class="muted" style="margin-top:12px;">Generado por ON English Academy Portal el {{ now()->format('d/m/Y H:i') }}.</p>
    @endif
    </div>
@endforeach
</body>
</html>
