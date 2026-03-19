@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Detalle de alumno</h1>
        <p class="page-subtitle">Ficha integral del alumno: nivel, cursos, pagos y seguimiento operativo</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('finance.students.history', $student) }}">Historial financiero</a>
        <a class="btn secondary" href="{{ route('students.edit', $student) }}">Editar datos</a>
        <a class="btn secondary" href="{{ route('students.index') }}">Volver</a>
    </div>
</div>

<section class="student-hero">
    <div class="student-hero-main">
        <div class="student-hero-profile">
            <span class="table-avatar student-hero-avatar">
                @if($student->profile_photo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($student->profile_photo_path) }}" alt="{{ $student->full_name }}">
                @else
                    {{ strtoupper(substr($student->first_name, 0, 1)) }}
                @endif
            </span>
            <div>
                <h2 class="student-hero-name">{{ $student->full_name }}</h2>
                <div class="student-hero-meta">{{ $student->email ?: 'Sin email' }}</div>
                <div class="student-hero-meta">{{ $student->campus?->name ?? 'Sin sede' }} · {{ $student->document_id ?: 'Sin documento' }}</div>
            </div>
        </div>
        <div class="student-hero-tags">
            @include('partials.ui.status-badge', ['tone' => $student->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($student->status)])
            @if($currentCourseLevel)
                @include('partials.ui.status-badge', ['tone' => 'level', 'text' => $currentCourseLevel->scale_position.'/'.$currentCourseLevel->scale_total.' · '.$currentCourseLevel->code])
            @endif
            @if(!is_null($summary['attendance_rate']))
                @include('partials.ui.status-badge', ['tone' => 'info', 'text' => 'Asistencia '.$summary['attendance_rate'].'%'])
            @endif
        </div>
    </div>
    <div class="student-hero-side">
        <div class="student-hero-side-label">Curso actual</div>
        <div class="student-hero-side-value">{{ $currentCourse?->name ?? 'Sin curso activo' }}</div>
        <div class="student-hero-side-sub">{{ $currentCourse?->period?->code ?? 'Sin período' }}</div>
        <div class="student-hero-side-sub">{{ $summary['completion_date'] ? 'Finaliza '.$summary['completion_date']->format('d/m/Y') : 'Sin fecha de cierre' }}</div>
    </div>
</section>

<div class="student-section-nav">
    <a href="#student-progress" class="student-section-pill">Progreso</a>
    <a href="#student-academic" class="student-section-pill">Académico</a>
    <a href="#student-finance" class="student-section-pill">Financiero</a>
    <a href="#student-audit" class="student-section-pill">Auditoría</a>
</div>

<div class="summary-grid student-summary-grid" id="student-progress">
    <div class="card summary-card">
        <div class="summary-label">Nivel actual</div>
        <div class="summary-value">{{ $summary['current_level_label'] }}</div>
        <div class="table-sub">{{ $summary['current_level_name'] }}</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Siguiente nivel</div>
        <div class="summary-value">{{ $nextCourseLevel?->code ?? 'N/D' }}</div>
        <div class="table-sub">{{ $summary['next_level_name'] }}</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Fin del curso actual</div>
        <div class="summary-value">{{ $summary['completion_date']?->format('d/m') ?? 'N/D' }}</div>
        <div class="table-sub">{{ $summary['reminder_date'] ? 'Recordatorio: '.$summary['reminder_date']->format('d/m/Y') : 'Sin recordatorio programable' }}</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Saldo pendiente</div>
        <div class="summary-value">${{ number_format($summary['outstanding_total'], 2) }}</div>
        <div class="table-sub">{{ is_null($summary['attendance_rate']) ? 'Asistencia N/D' : 'Asistencia '.$summary['attendance_rate'].'%' }}</div>
    </div>
</div>

<div class="detail-grid student-detail-grid" id="student-academic">
    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Datos del alumno</h2>
            <div class="entity-sub">Información base y contacto</div>
        </div>
        <div class="student-profile-head">
            <span class="table-avatar student-detail-avatar">
                @if($student->profile_photo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($student->profile_photo_path) }}" alt="{{ $student->full_name }}">
                @else
                    {{ strtoupper(substr($student->first_name, 0, 1)) }}
                @endif
            </span>
            <div>
                <div class="table-title">{{ $student->full_name }}</div>
                <div class="table-sub">{{ $student->email ?: 'Sin email' }}</div>
            </div>
        </div>
        <div class="detail-list detail-list-soft">
            <div><strong>Documento:</strong> {{ $student->document_id ?: 'Sin documento' }}</div>
            <div><strong>Teléfono:</strong> {{ $student->phone ?: 'Sin teléfono' }}</div>
            <div><strong>Sede:</strong> {{ $student->campus?->name ?? 'Sin sede' }}</div>
            <div><strong>Estado:</strong> {{ ucfirst($student->status) }}</div>
            <div><strong>Fecha inscripción:</strong> {{ $student->enrollment_date?->format('d/m/Y') ?? 'N/D' }}</div>
            <div><strong>Dirección:</strong> {{ $student->address ?: 'Sin dirección' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Curso actual y progresión</h2>
            <div class="entity-sub">Escala, CEFR y próxima transición</div>
        </div>
        @if($currentCourse)
            <div class="detail-list detail-list-soft">
                <div><strong>Curso:</strong> {{ $currentCourse->name }}</div>
                <div><strong>Profesor:</strong> {{ $currentCourse->teacher?->full_name ?? 'N/D' }}</div>
                <div><strong>Período:</strong> {{ $currentCourse->period?->code ?? 'N/D' }}</div>
                <div><strong>Horario:</strong> {{ $currentCourse->scheduleTemplate?->display_label ?? 'N/D' }}</div>
                <div><strong>Escala:</strong> {{ $currentCourseLevel ? $currentCourseLevel->scale_position.'/'.$currentCourseLevel->scale_total.' · '.$currentCourseLevel->name : 'Sin escala asignada' }}</div>
                <div><strong>Referencia CEFR:</strong> {{ $currentCourseLevel?->cefr_reference ?? 'N/D' }}</div>
                <div><strong>Detalle del nivel:</strong> {{ $currentCourseLevel?->description ?? 'Sin descripción' }}</div>
                <div><strong>Próximo nivel:</strong> {{ $nextCourseLevel?->name ?? 'No definido' }}</div>
                <div><strong>Fecha finalización:</strong> {{ $currentCourse->end_date?->format('d/m/Y') ?? 'N/D' }}</div>
            </div>
            @if($student->alerts->count() > 0)
                <div class="stack-sm">
                    @foreach($student->alerts as $alert)
                        <div class="flash {{ $alert->type === 'finance' ? 'err' : 'ok' }}">{{ $alert->message }}</div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="empty-state">El alumno no tiene un curso actual con nivel escalable asignado.</div>
        @endif
    </div>
</div>

<div class="card table-card" id="student-academic-history">
    <div class="section-head">
        <h2 class="section-title section-title-md">Histórico de cursos</h2>
        <div class="entity-sub">{{ $courseHistory->count() }} inscripción(es)</div>
    </div>
    @if($courseHistory->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Curso</th>
                    <th>Nivel</th>
                    <th>Profesor</th>
                    <th>Período</th>
                    <th>Fechas</th>
                    <th>Asistencia</th>
                    <th>Estado</th>
                </tr>
                </thead>
                <tbody>
                @foreach($courseHistory as $enrollment)
                    @php
                        $course = $enrollment->group?->course;
                        $level = $course?->courseLevel;
                        $attendanceRate = (int) $enrollment->attendance_records_count > 0
                            ? (int) round(($enrollment->present_attendance_count / $enrollment->attendance_records_count) * 100)
                            : null;
                    @endphp
                    <tr>
                        <td>
                            <div class="table-title">{{ $course?->name ?? 'N/D' }}</div>
                            <div class="table-sub">{{ $course?->code ?: 'Sin código' }}</div>
                        </td>
                        <td>{{ $level ? $level->scale_position.'/'.$level->scale_total.' · '.$level->name : ($course?->level?->name ?? 'N/D') }}</td>
                        <td>{{ $course?->teacher?->full_name ?? 'N/D' }}</td>
                        <td>{{ $course?->period?->code ?? 'N/D' }}</td>
                        <td>{{ $course?->start_date?->format('d/m/Y') ?? 'N/D' }} - {{ $course?->end_date?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td>{{ is_null($attendanceRate) ? 'N/D' : $attendanceRate.'%' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $enrollment->status === 'active' ? 'ok' : 'info', 'text' => ucfirst($enrollment->status)])</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">No hay histórico de cursos para este alumno.</div>
    @endif
</div>

<div class="detail-grid student-detail-grid" id="student-finance">
    <div class="card table-card">
        <div class="section-head">
            <h2 class="section-title section-title-md">Histórico de pagos</h2>
            <div class="entity-sub">${{ number_format($summary['paid_total'], 2) }} cobrados</div>
        </div>
        @if($paymentHistory->count() > 0)
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Método</th>
                        <th>Recibo</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($paymentHistory as $payment)
                        <tr>
                            <td>{{ ($payment->paid_at_datetime ?? $payment->paid_at)?->format('d/m/Y') ?? 'N/D' }}</td>
                            <td>${{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->method ?: 'Sin método' }}</td>
                            <td>
                                @if($payment->receipt)
                                    <a href="{{ route('finance.receipts.show', $payment->receipt) }}">{{ $payment->receipt->receipt_number }}</a>
                                @else
                                    Sin recibo
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">No hay pagos registrados.</div>
        @endif
    </div>

    <div class="card table-card">
        <div class="section-head">
            <h2 class="section-title section-title-md">Cargos y saldo</h2>
            <div class="entity-sub">${{ number_format($summary['charged_total'], 2) }} facturados</div>
        </div>
        @if($chargeHistory->count() > 0)
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Concepto</th>
                        <th>Curso</th>
                        <th>Monto</th>
                        <th>Saldo</th>
                        <th>Estado</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($chargeHistory as $charge)
                        <tr>
                            <td>{{ $charge->concept }}</td>
                            <td>{{ $charge->course?->name ?? 'N/D' }}</td>
                            <td>${{ number_format($charge->amount, 2) }}</td>
                            <td>${{ number_format(\App\Support\FinanceReconcile::outstandingForCharge($charge), 2) }}</td>
                            <td>@include('partials.ui.status-badge', ['tone' => $charge->status === 'paid' ? 'ok' : ($charge->status === 'overdue' ? 'danger' : 'warn'), 'text' => ucfirst($charge->status)])</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">No hay cargos registrados.</div>
        @endif
    </div>
</div>

<div id="student-audit">
@include('partials.ui.audit-timeline', ['auditLogs' => $auditLogs ?? collect()])
</div>
@endsection
