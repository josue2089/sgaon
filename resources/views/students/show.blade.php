@extends('layouts.app')
@section('content')
@php use App\Support\MoneyFormat; @endphp

<div class="module-head student-head">
    <div>
        <h1 class="page-title">Ficha del alumno</h1>
    </div>
    <div class="form-actions student-actions">
        <button class="btn" type="button" data-modal-open="enroll-modal">Inscribir en curso</button>
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

@if(session('offer_first_payment') && !empty($canManageStudentFinance))
    {{-- #450: alta → inscripción → pago seguido. --}}
    <div class="flash info first-payment-offer">
        <span>Se generó la primera cuota de la inscripción. ¿Quieres registrar su pago ahora?</span>
        <button class="btn" type="button" data-modal-open="student-payment-modal">Registrar pago de la primera cuota</button>
    </div>
@endif

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

@include('students.show.resumen')

@include('students.show.academico')

@include('students.show.finanzas')

@include('students.show.recuperativas')

@include('students.show.documentos')

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

@include('partials.enrollments.enroll-modal', ['groups' => $enrollableGroups ?? collect()])

@endsection
