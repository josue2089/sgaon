@extends('layouts.app')
@section('content')
@php use App\Support\MoneyFormat; @endphp

<div class="module-head student-head">
    <div>
        <h1 class="page-title">Ficha del alumno</h1>
    </div>
    <div class="form-actions student-actions">
        @if(\Illuminate\Support\Facades\Route::has('enrollments.create'))
            <a class="btn" href="{{ route('enrollments.create', ['student_id' => $student->id]) }}">Inscribir en curso</a>
        @endif
        @if(!empty($canManageStudentFinance))
            <button class="btn" type="button" data-modal-open="student-payment-modal">Registrar pago</button>
            <button class="btn secondary" type="button" data-modal-open="student-charge-modal">Nuevo cargo</button>
        @endif
        <a class="btn secondary" href="{{ route('students.makeups.create', $student) }}">Recuperativa</a>
        <a class="btn secondary" href="{{ route('students.edit', $student) }}">Editar</a>
        <details class="fi-menu student-print-menu">
            <summary class="btn secondary">Imprimir ▾</summary>
            <div class="fi-menu-panel">
                <a class="fi-menu-link" href="{{ route('students.enrollment-sheet', $student) }}">Ficha imprimible</a>
                <a class="fi-menu-link" href="{{ route('students.enrollment-sheet.pdf', $student) }}">Ficha PDF</a>
                @if($student->isExtracurricular())
                    <a class="fi-menu-link" href="{{ route('students.extracurricular-sheet.pdf', $student) }}">Planilla extracurricular (PDF)</a>
                @endif
            </div>
        </details>
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
            @include('partials.ui.status', ['status' => $student->status])
            @if($currentCourseLevel)
                @include('partials.ui.status-badge', ['tone' => 'level', 'text' => (($currentCourseLevel->sort_order ?? $currentCourseLevel->scale_position).'/'.($currentCourseLevel->program_total ?? $currentCourseLevel->scale_total)).' · '.$currentCourseLevel->code])
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

<?php
    $studentTabs = [
        'resumen' => ['Resumen', null],
        'academico' => ['Académico', $courseHistory->count()],
        'finanzas' => ['Finanzas', $chargeHistory->whereNull('voided_at')->whereIn('status', ['pending', 'partial', 'overdue'])->count() ?: null],
        'recuperativas' => ['Recuperativas', ($makeupHistory ?? collect())->count() ?: null],
        'documentos' => ['Documentos', $student->attachments->count() ?: null],
        'historial' => ['Historial', null],
    ];
?>
<div class="portal-tabs student-tabs" role="tablist" aria-label="Secciones de la ficha" data-tabs>
    @foreach($studentTabs as $key => [$label, $badge])
        <button type="button" class="portal-tab {{ $loop->first ? 'is-active' : '' }}" id="tab-{{ $key }}" data-tab-target="{{ $key }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="panel-{{ $key }}">
            {{ $label }}@if($badge)<span class="portal-tab-badge">{{ $badge }}</span>@endif
        </button>
    @endforeach
</div>

<section class="portal-tab-panel student-panel is-active" id="panel-resumen" data-tab-panel="resumen" role="tabpanel" aria-labelledby="tab-resumen">
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
        <div class="summary-value">{{ MoneyFormat::usd($summary['outstanding_total']) }}</div>
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
            <div><strong>N° Contrato:</strong> {{ $student->contract_number ?: 'N/D' }}</div>
            <div><strong>Documento:</strong> {{ $student->document_id ?: 'Sin documento' }}</div>
            <div><strong>Celular:</strong> {{ $student->mobile_phone ?: $student->phone ?: 'Sin teléfono' }}</div>
            <div><strong>Teléfono:</strong> {{ $student->landline_phone ?: 'Sin teléfono fijo' }}</div>
            <div><strong>Sede:</strong> {{ $student->campus?->name ?? 'Sin sede' }}</div>
            <div><strong>Programa inscripción:</strong> {{ $student->registrationProgram?->name ?? 'N/D' }}</div>
            <div><strong>Estado:</strong> {{ \App\Support\StatusLabel::label($student->status) }}</div>
            <div><strong>Edad:</strong> {{ $student->age ? $student->age.' años' : 'N/D' }}</div>
            <div><strong>Fecha inscripción:</strong> {{ $student->enrollment_date?->format('d/m/Y') ?? 'N/D' }}</div>
            <div><strong>Familiar en institución:</strong> {{ $student->family_in_institution ? 'Sí' : 'No' }}{{ $student->family_in_institution_details ? ' · '.$student->family_in_institution_details : '' }}</div>
            <div><strong>Dirección:</strong> {{ $student->address ?: 'Sin dirección' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Curso actual y progresión</h2>
            <div class="entity-sub">Programa, nivel real y próxima transición</div>
        </div>
        @if($currentCourse)
            <div class="detail-list detail-list-soft">
                <div><strong>Curso:</strong> {{ $currentCourse->name }}</div>
                <div><strong>Programa:</strong> {{ $currentCourse->program?->name ?? 'N/D' }}</div>
                <div><strong>Profesor:</strong> {{ $currentCourse->teacher?->full_name ?? 'N/D' }}</div>
                <div><strong>Período:</strong> {{ $currentCourse->period?->code ?? 'N/D' }}</div>
                <div><strong>Horario:</strong> {{ $currentCourse->scheduleTemplate?->display_label ?? 'N/D' }}</div>
                <div><strong>Nivel real:</strong> {{ $currentCourseLevel ? (($currentCourseLevel->sort_order ?? $currentCourseLevel->scale_position).'/'.($currentCourseLevel->program_total ?? $currentCourseLevel->scale_total).' · '.$currentCourseLevel->name) : 'Sin nivel asignado' }}</div>
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

<div class="detail-grid student-detail-grid">
    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Representante principal</h2>
            <div class="entity-sub">Datos de contacto y trabajo</div>
        </div>
        @php($representative = $student->representatives->first())
        @if($representative)
            <div class="detail-list detail-list-soft">
                <div><strong>Nombre:</strong> {{ $representative->full_name }}</div>
                <div><strong>Cédula:</strong> {{ $representative->nationality ? $representative->nationality.'-' : '' }}{{ $representative->document_id ?: 'N/D' }}</div>
                <div><strong>Parentesco:</strong> {{ $representative->relation ?: 'N/D' }}</div>
                <div><strong>Email:</strong> {{ $representative->email ?: 'N/D' }}</div>
                <div><strong>Teléfono habitación:</strong> {{ $representative->home_phone ?: 'N/D' }}</div>
                <div><strong>Celular:</strong> {{ $representative->mobile_phone ?: $representative->phone ?: 'N/D' }}</div>
                <div><strong>Teléfono oficina:</strong> {{ $representative->office_phone ?: 'N/D' }}</div>
                <div><strong>Lugar de trabajo:</strong> {{ $representative->work_place ?: 'N/D' }}</div>
                <div><strong>Dirección oficina:</strong> {{ $representative->work_address ?: 'N/D' }}</div>
                <div><strong>Dirección habitación:</strong> {{ $representative->address ?: 'N/D' }}</div>
            </div>
        @else
            <div class="empty-state">No hay representante principal registrado.</div>
        @endif
    </div>

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Personas autorizadas</h2>
            <div class="entity-sub">Contactos autorizados del alumno</div>
        </div>
        @if($student->authorizedContacts->count() > 0)
            <div class="detail-list detail-list-soft">
                @foreach($student->authorizedContacts as $contact)
                    <div>
                        <strong>Autorizado {{ $contact->slot }}:</strong> {{ $contact->full_name ?: 'N/D' }}<br>
                        Cédula: {{ $contact->document_id ?: 'N/D' }} · Parentesco: {{ $contact->relationship ?: 'N/D' }}<br>
                        Habitación: {{ $contact->home_phone ?: 'N/D' }} · Celular: {{ $contact->mobile_phone ?: 'N/D' }}<br>
                        Trabajo: {{ $contact->work_place ?: 'N/D' }} · Dirección: {{ $contact->work_address ?: 'N/D' }}<br>
                        Habitación: {{ $contact->address ?: 'N/D' }}
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state">No hay personas autorizadas registradas.</div>
        @endif
    </div>
</div>

<div class="detail-grid student-detail-grid">
    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Ficha médica</h2>
            <div class="entity-sub">Alergias, tratamientos y autorizaciones</div>
        </div>
        <div class="detail-list detail-list-soft">
            <div><strong>Alergias:</strong> {{ $student->medical_has_allergies ? 'Sí' : 'No' }}{{ $student->medical_allergy_details ? ' · '.$student->medical_allergy_details : '' }}</div>
            <div><strong>Tratamiento actual:</strong> {{ $student->medical_has_treatment ? 'Sí' : 'No' }}{{ $student->medical_treatment_details ? ' · '.$student->medical_treatment_details : '' }}</div>
            <div><strong>Medicamento fiebre:</strong> {{ $student->medical_fever_medication ?: 'N/D' }}</div>
            <div><strong>Medicamento cefalea:</strong> {{ $student->medical_headache_medication ?: 'N/D' }}</div>
            <div><strong>Observaciones:</strong> {{ $student->medical_notes ?: 'Sin observaciones' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Datos comerciales</h2>
            <div class="entity-sub">Seguimiento de venta e inscripción</div>
        </div>
        <div class="detail-list detail-list-soft">
            <div><strong>Vendedor:</strong> {{ $student->salesperson ?: 'N/D' }}</div>
            <div><strong>Promoción:</strong> {{ $student->promotion ?: 'N/D' }}</div>
            <div><strong>Método de pago:</strong> {{ $student->payment_method ?: 'N/D' }}</div>
            <div><strong>Cuotas:</strong> {{ $student->installments ?: 'N/D' }}</div>
            <div><strong>Observaciones:</strong> {{ $student->commercial_notes ?: 'Sin observaciones' }}</div>
        </div>
    </div>
</div>
</section>

<section class="portal-tab-panel student-panel" id="panel-academico" data-tab-panel="academico" role="tabpanel" aria-labelledby="tab-academico">
<div class="card" id="student-evaluations">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Evaluaciones por curso</h2>
        <div class="entity-sub">Histórico de rubros por informe</div>
    </div>
    @forelse($gradeEvaluationHistory as $entry)
        @php($evalSet = $entry->evaluationSet)
        @php($gcourse = $evalSet?->course)
        <article class="grades-student-entry">
            <div class="grades-student-entry-head">
                <div>
                    <div class="table-title">{{ $gcourse?->name ?? 'Curso' }}</div>
                    <div class="table-sub">{{ $evalSet?->evaluated_on?->format('d/m/Y') ?? '' }} @if($evalSet?->title)· {{ $evalSet->title }}@endif</div>
                </div>
                @if($gcourse)
                    <a class="btn secondary" href="{{ route('courses.grades.index', $gcourse) }}">Evaluaciones del curso</a>
                @endif
            </div>
            <div class="grades-student-rubrics">
                @foreach(\App\Support\GradeRubric::SKILL_KEYS as $skill)
                    @php($rating = $entry->ratingForSkill($skill))
                    <div class="grades-student-rubric">
                        <span>{{ \App\Support\GradeRubric::SKILL_LABELS_ES[$skill] }}</span>
                        @include('partials.ui.status-badge', ['tone' => \App\Support\GradeRubric::ratingTone($rating), 'text' => \App\Support\GradeRubric::RATING_LABELS_ES[$rating] ?? $rating])
                    </div>
                @endforeach
            </div>
            @if($entry->observations)
                <div class="grades-student-obs">{{ $entry->observations }}</div>
            @endif
        </article>
    @empty
        <div class="empty-state-inline">Sin evaluaciones registradas.</div>
    @endforelse
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
                @forelse($courseHistory as $enrollment)
                    @php($course = optional($enrollment->group)->course)
                    @php($level = $course?->programLevel ?: $course?->courseLevel)
                    @php($attendanceRate = (int) $enrollment->attendance_records_count > 0
                        ? (int) round(($enrollment->present_attendance_count / $enrollment->attendance_records_count) * 100)
                        : null)
                    @php($levelLabel = $level
                        ? (($level->sort_order ?? $level->scale_position).'/'.($level->program_total ?? $level->scale_total).' - '.$level->name)
                        : ($course?->level?->name ?? 'N/D'))
                    <tr>
                        <td>
                            <div class="table-title">{{ $course?->name ?? 'N/D' }}</div>
                            <div class="table-sub">{{ $course?->code ?: 'Sin codigo' }}</div>
                        </td>
                        <td>{{ $levelLabel }}</td>
                        <td>{{ $course?->teacher?->full_name ?? 'N/D' }}</td>
                        <td>{{ $course?->period?->code ?? 'N/D' }}</td>
                        <td>{{ $course?->start_date?->format('d/m/Y') ?? 'N/D' }} - {{ $course?->end_date?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td>{{ is_null($attendanceRate) ? 'N/D' : $attendanceRate.'%' }}</td>
                        <td>@include('partials.ui.status', ['status' => $enrollment->status, 'domain' => 'enrollment'])</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state-inline">No hay historico de cursos para este alumno.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

    @else
        <div class="empty-state">No hay historico de cursos para este alumno.</div>
    @endif
</div>

@if($student->isExtracurricular() || $student->school_grade || $student->extracurricular_level || $student->teacher_observations)
<div class="card" id="student-extracurricular">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Actividad extracurricular</h2>
        <div class="entity-sub">Datos de la planilla de inscripción</div>
    </div>
    <div class="detail-list detail-list-soft">
        <div><strong>Grado / Año:</strong> {{ $student->school_grade ?: 'N/D' }} · <strong>Sección:</strong> {{ $student->school_section ?: 'N/D' }}</div>
        <div><strong>Edad:</strong> {{ $student->age ? $student->age.' años' : 'N/D' }}</div>
        <div><strong>Teléfono de emergencia:</strong> {{ $student->emergency_phone ?: 'N/D' }}</div>
        <div><strong>Nivel / Dominio actual:</strong> {{ $student->extracurricular_level ?: 'N/D' }}</div>
        <div><strong>Objetivos / Competencias:</strong> {{ $student->extracurricular_objectives ?: 'N/D' }}</div>
        <div><strong>Observaciones del docente:</strong> {{ $student->teacher_observations ?: 'Sin observaciones' }}</div>
        <div><strong>Condición de pago / Matrícula:</strong> {{ $student->payment_condition ?: 'N/D' }}</div>
    </div>
</div>
@endif
</section>

<section class="portal-tab-panel student-panel" id="panel-finanzas" data-tab-panel="finanzas" role="tabpanel" aria-labelledby="tab-finanzas">
<div class="section-head">
    <div class="entity-sub">Pagos, cargos y saldo del alumno.</div>
    <a class="btn secondary" href="{{ route('finance.students.history', $student) }}">Historial financiero completo</a>
</div>
<div class="detail-grid student-detail-grid" id="student-finance">
    <div class="card table-card">
        <div class="section-head">
            <h2 class="section-title section-title-md">Histórico de pagos</h2>
            <div class="entity-sub">{{ MoneyFormat::usd($summary['paid_total']) }} cobrados</div>
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
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($paymentHistory as $payment)
                        <tr>
                            <td>{{ ($payment->paid_at_datetime ?? $payment->paid_at)?->format('d/m/Y') ?? 'N/D' }}</td>
                            <td>{{ MoneyFormat::formatLedgerAmount((float) $payment->amount, $payment->currency) }}</td>
                            <td>{{ $payment->method ?: 'Sin método' }}</td>
                            <td>
                                @if($payment->receipt)
                                    <a href="{{ route('finance.receipts.show', $payment->receipt) }}">{{ $payment->receipt->receipt_number }}</a>
                                @else
                                    Sin recibo
                                @endif
                            </td>
                            <td>@include('partials.finance.void-payment', ['payment' => $payment])</td>
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
            <div class="entity-sub">{{ MoneyFormat::usd($summary['charged_total']) }} facturados</div>
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
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($chargeHistory as $charge)
                        <tr>
                            <td>{{ $charge->concept }}</td>
                            <td>{{ $charge->course?->name ?? 'N/D' }}</td>
                            <td>{{ MoneyFormat::chargeAmount($charge) }}</td>
                            <td>{{ MoneyFormat::formatLedgerAmount(\App\Support\FinanceReconcile::outstandingForCharge($charge), $charge->currency) }}</td>
                            <td>
                                @if($charge->voided_at)
                                    <span class="table-sub">{{ $charge->void_reason ?: 'Anulado' }}</span>
                                @else
                                    @include('partials.ui.status', ['status' => $charge->status])
                                @endif
                            </td>
                            <td>@include('partials.finance.void-charge', ['charge' => $charge])</td>
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
</section>

<section class="portal-tab-panel student-panel" id="panel-recuperativas" data-tab-panel="recuperativas" role="tabpanel" aria-labelledby="tab-recuperativas">
<div class="card table-card" id="student-makeups">
    <div class="section-head">
        <h2 class="section-title section-title-md">Clases recuperativas</h2>
        <a href="{{ route('students.makeups.create', $student) }}">Registrar clase recuperativa</a>
    </div>
    @if(($makeupHistory ?? collect())->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Clase perdida</th><th>Recuperativa</th><th>Profesor</th><th>Estado</th><th>Cargo</th></tr></thead>
                <tbody>
                @foreach($makeupHistory as $makeup)
                    @php($makeupSession = $makeup->booking?->makeupSession)
                    <tr>
                        <td>{{ $makeup->missedSession?->session_date?->format('d/m/Y') ?? 'Sin inasistencia (manual)' }}<div class="table-sub">{{ $makeup->enrollment?->group?->course?->name }}</div></td>
                        <td>{{ $makeupSession ? $makeupSession->session_date?->format('d/m/Y').' · '.substr((string) $makeupSession->starts_at, 0, 5).' - '.substr((string) $makeupSession->ends_at, 0, 5) : 'Sin reservar' }}</td>
                        <td>{{ $makeupSession?->teacher?->full_name ?? '—' }}</td>
                        <td>{{ \App\Support\StatusLabel::label($makeup->status, 'makeup') }}</td>
                        <td>
                            @if($makeup->charge)
                                {{ MoneyFormat::formatLedgerAmount((float) $makeup->charge->amount, $makeup->charge->currency) }}
                                <div class="table-sub">{{ $makeup->charge->voided_at ? 'Anulado' : \App\Support\StatusLabel::label($makeup->charge->status) }}</div>
                            @else
                                Sin cargo
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">No hay clases recuperativas registradas.</div>
    @endif
</div>
</section>

<section class="portal-tab-panel student-panel" id="panel-documentos" data-tab-panel="documentos" role="tabpanel" aria-labelledby="tab-documentos">
<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title section-title-md">Adjuntos del alumno</h2>
        <div class="entity-sub">{{ $student->attachments->count() }} archivo(s)</div>
    </div>
    <form method="POST" action="{{ route('students.attachments.store', $student) }}" enctype="multipart/form-data" class="fi-filter-bar">
        @csrf
        <input type="text" name="title" placeholder="Título del documento" required>
        <select class="max-w-[220px]" name="category">
            <option value="general">General</option>
            <option value="identity">Cédula</option>
            <option value="medical">Médico</option>
            <option value="contract">Contrato</option>
            <option value="payment">Pago</option>
        </select>
        <input type="file" name="file" required>
        <button class="btn" type="submit">Cargar adjunto</button>
    </form>
    @if($student->attachments->count() > 0)
        <div class="table-wrap" style="margin-top: 1rem;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Título</th>
                    <th>Categoría</th>
                    <th>Archivo</th>
                    <th>Tamaño</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($student->attachments as $attachment)
                    <tr>
                        <td class="table-title">{{ $attachment->title }}</td>
                        <td>{{ \App\Support\StatusLabel::category($attachment->category) }}</td>
                        <td>{{ $attachment->original_name }}</td>
                        <td>{{ $attachment->file_size_label }}</td>
                        <td>{{ $attachment->created_at?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td class="table-actions">
                            <a href="{{ route('students.attachments.download', [$student, $attachment]) }}">Descargar</a>
                            <form method="POST" action="{{ route('students.attachments.destroy', [$student, $attachment]) }}" data-confirm="¿Eliminar este adjunto?" data-confirm-danger>
                                @csrf
                                @method('DELETE')
                                <button class="btn-link-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">No hay adjuntos cargados.</div>
    @endif
</div>
</section>

<section class="portal-tab-panel student-panel" id="panel-historial" data-tab-panel="historial" role="tabpanel" aria-labelledby="tab-historial">
<div id="student-audit">
@include('partials.ui.audit-timeline', ['auditLogs' => $auditLogs ?? collect()])
</div>
</section>

@if(!empty($canManageStudentFinance))
    <?php
        // Si el envío falló, se reabre el modal del formulario que tenía el error.
        $reopen = $errors->any() ? (old('concept') !== null ? 'student-charge-modal' : (old('paid_at') !== null || old('charge_ids') !== null ? 'student-payment-modal' : null)) : null;
    ?>
    <x-ui.modal id="student-charge-modal" title="Nuevo cargo" subtitle="Se registra a nombre de {{ $student->full_name }}" size="md" :data-open-on-load="$reopen === 'student-charge-modal' ? '1' : null">
        @include('partials.finance.register-charge-form', [
                'formAction' => route('students.charges.store', $student),
                'formId' => 'student-charge-form',
                'lockStudent' => true,
                'studentId' => $student->id,
                'enrollments' => $studentEnrollments,
            ])
    </x-ui.modal>
    <x-ui.modal id="student-payment-modal" title="Registrar pago" subtitle="Se registra a nombre de {{ $student->full_name }}" size="lg" :data-open-on-load="$reopen === 'student-payment-modal' ? '1' : null">
        @include('partials.finance.register-payment-form', [
                'formAction' => route('students.payments.store', $student),
                'formId' => 'student-payment-form',
                'prefix' => 'student-payment',
                'lockStudent' => true,
                'studentId' => $student->id,
                'charges' => $payableCharges,
                'paymentMethods' => $paymentMethods,
                'bcvRate' => $bcvRate,
                'bcvEurRate' => $bcvEurRate,
            ])
    </x-ui.modal>
@endif

@endsection
