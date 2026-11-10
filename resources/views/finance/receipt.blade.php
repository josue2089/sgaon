@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Recibo {{ $receipt->receipt_number }}</h1>
        <p class="page-subtitle">Detalle exacto de cargos aplicados por este pago</p>
    </div>
    <div class="form-actions">
        <button class="btn secondary" type="button" onclick="window.print()">Imprimir</button>
        <a class="btn secondary" href="{{ route('finance.receipts.pdf', $receipt) }}">Exportar PDF</a>
        @unless($payment->voided_at)
            @php
                $payment->loadMissing('student.representatives');
                $contact = $payment->student?->representatives->first(fn ($rep) => filled($rep->mobile_phone ?: $rep->phone));
                $whatsappPhone = $contact ? ($contact->mobile_phone ?: $contact->phone) : ($payment->student?->mobile_phone ?: $payment->student?->phone);
                $whatsappText = 'Hola, ON English Academy confirma el pago de '.($payment->student->full_name ?? 'el alumno')
                    .': recibo '.$receipt->receipt_number.' por '.\App\Support\MoneyFormat::dualLine($payment)
                    .', del '.\App\Support\DateFormat::date($receipt->issued_at ?? $payment->paid_at).'. ¡Gracias!';
            @endphp
            <form method="POST" action="{{ route('finance.receipts.resend', $receipt) }}" data-guard-submit>
                @csrf
                <button class="btn secondary" type="submit" data-submit-busy-label="Enviando…">Reenviar por correo</button>
            </form>
            <a class="btn secondary" href="{{ \App\Support\WhatsappLink::to($whatsappPhone, $whatsappText) }}" target="_blank" rel="noopener"
               title="{{ \App\Support\WhatsappLink::normalizePhone($whatsappPhone) ? 'Abre WhatsApp con el mensaje listo' : 'No hay teléfono registrado: elige el contacto en WhatsApp' }}">Compartir por WhatsApp</a>
        @endunless
        <a class="btn secondary" href="{{ route('finance.students.history', $payment->student) }}">Historial del alumno</a>
        @include('partials.finance.void-payment', ['payment' => $payment])
        <a class="btn secondary" href="{{ route('finance.index') }}">Volver</a>
    </div>
</div>

@if($payment->voided_at)
    <div class="flash warn">
        Pago ANULADO el {{ $payment->voided_at->format('d/m/Y') }}{{ $payment->voidedBy ? ' por '.$payment->voidedBy->name : '' }}. Motivo: {{ $payment->void_reason ?: 'Sin motivo' }}
    </div>
@endif

<div class="detail-grid">
    <div class="card">
        <h2 class="section-title">Datos del recibo</h2>
        <div class="detail-list">
            <div><strong>Número:</strong> {{ $receipt->receipt_number }}</div>
            <div><strong>Alumno:</strong> {{ $payment->student->full_name ?? 'N/D' }}</div>
            <div><strong>Fecha:</strong> {{ $receipt->issued_at?->format('d/m/Y') ?? 'N/D' }}</div>
            <div><strong>Monto pagado:</strong> {{ \App\Support\MoneyFormat::dualLine($payment) }}</div>
            <div><strong>Método:</strong> {{ $payment->method ?: 'Sin método' }}</div>
            <div><strong>Referencia:</strong> {{ $payment->reference ?: 'Sin referencia' }}</div>
            <div><strong>Recibido por:</strong> {{ $payment->receivedBy?->name ?? 'N/D' }}</div>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Resumen de aplicación</h2>
        <div class="detail-list">
            <div><strong>Total cargos impactados:</strong> {{ $allocations->count() }}</div>
            <div><strong>Total aplicado:</strong> {{ \App\Support\MoneyFormat::formatLedgerAmount($allocations->sum(fn ($item) => (float) ($item->amount_applied ?? 0)), $payment->currency) }}</div>
            <div><strong>Observación:</strong> {{ $payment->notes ?: 'Sin observaciones' }}</div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title">Cargos aplicados</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Concepto</th>
                <th>Curso</th>
                <th>Nivel</th>
                <th>Grupo</th>
                <th>Período</th>
                <th>Monto aplicado</th>
            </tr>
            </thead>
            <tbody>
            @foreach($allocations as $allocation)
                @php
                    $charge = $allocation->charge;
                    $enrollment = $charge?->enrollment;
                    $courseName = $charge?->course?->name
                        ?? $enrollment?->group?->course?->name
                        ?? 'Sin curso';
                    $levelName = $charge?->course?->programLevel?->name
                        ?? $enrollment?->group?->course?->programLevel?->name
                        ?? 'Sin nivel';
                    $groupName = $charge?->group?->name
                        ?? $enrollment?->group?->name
                        ?? 'Sin grupo';
                    $periodCode = $charge?->period?->code
                        ?? $charge?->billing_period_label
                        ?? $enrollment?->group?->course?->period?->code
                        ?? 'Sin período';
                @endphp
                <tr>
                    <td>{{ $charge->concept ?? 'Cargo legacy' }}</td>
                    <td>{{ $courseName }}</td>
                    <td>{{ $levelName }}</td>
                    <td>{{ $groupName }}</td>
                    <td>{{ $periodCode }}</td>
                    <td>{{ \App\Support\MoneyFormat::formatLedgerAmount((float) ($allocation->amount_applied ?? 0), $charge?->currency) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
