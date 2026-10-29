@php use App\Support\MoneyFormat; @endphp
@extends('layouts.app')
@section('content')
<?php
    $baseQuery = array_filter(['student_id' => $focusStudentId, 'campus_id' => $filters['campus_id'] ?? null]);
    $tabUrl = fn (string $key) => route('finance.index', $baseQuery + ['tab' => $key]);
    $reopen = $focusStudent && $errors->any()
        ? (old('concept') !== null ? 'finance-charge-modal' : (old('paid_at') !== null || old('charge_ids') !== null ? 'finance-payment-modal' : null))
        : null;
?>
<div class="module-head">
    <div>
        <h1 class="page-title">Finanzas</h1>
        <p class="page-subtitle">
            Cobranza, pagos y comprobantes
            @if(($bcvRate['rate'] ?? 0) > 0) · Tasa BCV USD: Bs {{ MoneyFormat::rate($bcvRate['rate']) }}@endif
            @if(($bcvEurRate['rate'] ?? 0) > 0) · EUR: Bs {{ MoneyFormat::rate($bcvEurRate['rate']) }}@endif
        </p>
    </div>
    <div class="form-actions">
        @if($focusStudent)
            <button class="btn" type="button" data-modal-open="finance-payment-modal">Registrar pago</button>
            <button class="btn secondary" type="button" data-modal-open="finance-charge-modal">Nuevo cargo</button>
        @else
            <button class="btn" type="button" data-modal-open="finance-pick-student" data-pick-intent="payment">Registrar pago</button>
            <button class="btn secondary" type="button" data-modal-open="finance-pick-student" data-pick-intent="charge">Nuevo cargo</button>
        @endif
    </div>
</div>

@if($focusStudent)
    <div class="flash info finance-focus">
        Viendo solo a <strong>{{ $focusStudent->full_name }}</strong>{{ $focusStudent->document_id ? ' · '.$focusStudent->document_id : '' }}.
        <a href="{{ route('students.show', $focusStudent) }}#finanzas">Ficha del alumno</a> ·
        <a href="{{ route('finance.index', ['tab' => $tab]) }}">Ver todos</a>
    </div>
@endif

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Alumnos en mora', 'value' => $kpis['overdueStudents'], 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Mora crítica (30+ días)', 'value' => $kpis['criticalOverdue'], 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Comprobantes por validar', 'value' => $kpis['pendingProofs']])
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Cobrado este mes', 'value' => MoneyFormat::usd($kpis['monthPayments'])])
</div>

<nav class="section-tabs" aria-label="Secciones de finanzas">
    @foreach(['cobrar' => 'Por cobrar', 'pagos' => 'Pagos', 'comprobantes' => 'Comprobantes por validar'] as $key => $label)
        <a href="{{ $tabUrl($key) }}" @class(['section-tab', 'is-active' => $tab === $key]) aria-current="{{ $tab === $key ? 'page' : 'false' }}">
            {{ $label }}@if($key === 'comprobantes' && $kpis['pendingProofs'] > 0)<span class="portal-tab-badge">{{ $kpis['pendingProofs'] }}</span>@endif
        </a>
    @endforeach
    @if(\Illuminate\Support\Facades\Route::has('finance.summary'))
        <a href="{{ route('finance.summary') }}" class="section-tab">Resumen</a>
    @endif
</nav>

<form method="GET" action="{{ route('finance.index') }}" class="card finance-filters">
    <input type="hidden" name="tab" value="{{ $tab }}">
    @if($focusStudentId)<input type="hidden" name="student_id" value="{{ $focusStudentId }}">@endif
    <div class="fi-filter-bar">
        @if($campuses->count() > 1)
            <select name="campus_id" aria-label="Sede">
                <option value="">Todas las sedes</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((int) ($filters['campus_id'] ?? 0) === $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        @endif
        @if($tab === 'comprobantes')
            <select name="status" aria-label="Estado">
                @foreach(['pending_validation', 'approved', 'rejected'] as $option)
                    <option value="{{ $option }}" @selected(($filters['status'] ?? 'pending_validation') === $option)>{{ \App\Support\StatusLabel::label($option) }}</option>
                @endforeach
            </select>
        @else
            <select name="status" aria-label="Estado">
                <option value="">{{ $tab === 'cobrar' ? 'Con saldo (pendiente, abonado, vencido)' : 'Todos los pagos' }}</option>
                @foreach($tab === 'cobrar' ? ['pending', 'partial', 'overdue', 'paid', 'voided'] : ['voided'] as $option)
                    <option value="{{ $option }}" @selected(($filters['status'] ?? '') === $option)>{{ $option === 'voided' ? 'Anulados' : \App\Support\StatusLabel::label($option) }}</option>
                @endforeach
            </select>
            <input type="month" name="month" value="{{ $filters['month'] ?? '' }}" aria-label="Mes">
            <select name="currency" aria-label="Moneda">
                <option value="">Todas las monedas</option>
                @foreach($tab === 'cobrar' ? ['USD', 'EUR'] : ['USD', 'EUR', 'VES'] as $currency)
                    <option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        @endif
        <button class="btn" type="submit">Filtrar</button>
        @if($tab === 'cobrar')
            <a class="btn secondary" href="{{ route('finance.index', array_merge(request()->query(), ['export' => 'mora_csv'])) }}">Exportar CSV</a>
        @endif
    </div>
</form>

@if($tab === 'cobrar')
<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title section-title-sm">{{ $showVoided ? 'Cargos anulados' : 'Cuentas por cobrar' }}</h2>
        <div class="entity-sub">{{ $charges->total() }} cargo(s)</div>
    </div>
    <div class="table-wrap">
    <table>
        <thead>
        <tr><th>Alumno</th><th>Curso</th><th>Grupo</th><th>Período</th><th>Concepto</th><th>Monto</th><th>Pagado</th><th>Saldo</th><th>Mora</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
        @forelse($charges as $charge)
            @php
                $daysOverdue = (int) ($charge->days_overdue ?? 0);
                $moraTone = $daysOverdue >= 30 ? 'danger' : ($daysOverdue >= 10 ? 'warn' : ($daysOverdue > 0 ? 'info' : 'ok'));
                $moraText = $daysOverdue > 0 ? $daysOverdue.' día(s)' : 'Al día';
                $paidTotal = \App\Support\FinanceReconcile::paidTotalForCharge($charge);
                $balance = \App\Support\FinanceReconcile::outstandingForCharge($charge);
            @endphp
            <tr>
                <td><a href="{{ route('finance.students.history', $charge->student) }}">{{ $charge->student->full_name ?? '' }}</a></td>
                <td>{{ $charge->course->name ?? 'Sin curso' }}</td>
                <td>{{ $charge->group->name ?? 'Sin grupo' }}</td>
                <td>{{ $charge->period->code ?? ($charge->billing_period_label ?: 'Sin período') }}</td>
                <td>{{ $charge->concept }}</td>
                <td>{{ \App\Support\MoneyFormat::chargeAmount($charge, $charge->isEur() ? ($bcvEurRate['rate'] ?? 0) : ($bcvRate['rate'] ?? 0)) }}</td>
                <td>{{ \App\Support\MoneyFormat::formatLedgerAmount($paidTotal, $charge->currencyCode()) }}</td>
                <td>{{ \App\Support\MoneyFormat::formatLedgerAmount($balance, $charge->currencyCode()) }}</td>
                <td>@include('partials.ui.status-badge', ['tone' => $moraTone, 'text' => $moraText])</td>
                <td>
                    @if($charge->voided_at)
                        <div class="table-sub">{{ $charge->voided_at->format('d/m/Y') }} · {{ $charge->void_reason ?: 'Sin motivo' }}</div>
                    @else
                        @include('partials.ui.status', ['status' => $charge->status])
                    @endif
                </td>
                <td>@include('partials.finance.void-charge', ['charge' => $charge])</td>
            </tr>
        @empty
            <tr>
                <td colspan="11">
                    <div class="empty-state-inline">No hay cargos para el contexto seleccionado.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>
    @if($charges->hasPages())
        {{ $charges->links() }}
    @endif
</div>
@elseif($tab === 'pagos')
<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title section-title-sm">Pagos y recibos</h2>
        <div class="entity-sub">{{ $payments->total() }} pago(s)</div>
    </div>
    <div class="table-wrap">
    <table>
        <thead>
        <tr><th>Alumno</th><th>Moneda</th><th>Monto original</th><th>Monto aplicado</th><th>Tasa</th><th>Fecha</th><th>Recibo</th><th></th></tr>
        </thead>
        <tbody>
        @forelse($payments as $payment)
            <tr>
                <td><a href="{{ route('finance.students.history', $payment->student) }}">{{ $payment->student->full_name ?? '' }}</a></td>
                <td>{{ $payment->currency ?? 'USD' }}</td>
                <td>
                    @if(($payment->currency ?? 'USD') === 'VES')
                        {{ \App\Support\MoneyFormat::ves((float) ($payment->original_amount ?? $payment->amount)) }}
                    @elseif(($payment->currency ?? 'USD') === 'EUR')
                        {{ \App\Support\MoneyFormat::eur((float) ($payment->original_amount ?? $payment->amount)) }}
                    @else
                        {{ \App\Support\MoneyFormat::usd((float) ($payment->original_amount ?? $payment->amount)) }}
                    @endif
                </td>
                <td>{{ \App\Support\MoneyFormat::formatLedgerAmount((float) $payment->amount, $payment->currency) }}</td>
                <td>{{ $payment->exchange_rate ? MoneyFormat::rate((float) $payment->exchange_rate) : '—' }}</td>
                <td>{{ $payment->paid_at?->format('Y-m-d') }}</td>
                <td>
                    @if($payment->receipt)
                        <a href="{{ route('finance.receipts.show', $payment->receipt) }}">{{ $payment->receipt->receipt_number }}</a>
                    @endif
                    @if($payment->allocations->count() > 0)
                        <div class="table-sub">{{ $payment->allocations->count() }} cargo(s)</div>
                    @elseif($payment->charge_id)
                        <div class="table-sub">1 cargo</div>
                    @endif
                </td>
                <td>@include('partials.finance.void-payment', ['payment' => $payment])</td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    <div class="empty-state-inline">Aún no hay pagos registrados.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>
    @if($payments->hasPages())
        {{ $payments->links() }}
    @endif
</div>
@else
<div class="card finance-payment-requests-card" id="comprobantes">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-sm">Comprobantes enviados desde el portal</h2>
        <div class="entity-sub">Revisa el comprobante en pantalla y aprueba o rechaza.</div>
    </div>
    <div class="finance-payment-request-list">
        @forelse($paymentRequests as $paymentRequest)
            @php
                $proofUrl = route('files.payment-proof', $paymentRequest);
                $proofMime = strtolower((string) ($paymentRequest->proof_mime_type ?? ''));
                $proofExt = strtolower(pathinfo((string) $paymentRequest->proof_path, PATHINFO_EXTENSION));
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif', 'svg'];
                $isImage = str_starts_with($proofMime, 'image/')
                    || ($proofMime === '' && in_array($proofExt, $imageExtensions, true))
                    || ($proofMime === 'application/octet-stream' && in_array($proofExt, $imageExtensions, true));
                $isPdf = $proofMime === 'application/pdf'
                    || str_ends_with(strtolower($paymentRequest->proof_path), '.pdf');
            @endphp
            <article class="finance-payment-request-card">
                <div class="finance-payment-request-head">
                    <div>
                        <div class="table-title">{{ $paymentRequest->student?->full_name ?? 'N/D' }}</div>
                        <div class="table-sub">{{ $paymentRequest->representative?->full_name ? 'Enviado por representante: '.$paymentRequest->representative->full_name : 'Enviado por alumno' }}</div>
                        @if($paymentRequest->submitted_at)
                            <div class="table-sub">Recibido: {{ $paymentRequest->submitted_at->format('d/m/Y H:i') }}</div>
                        @endif
                    </div>
                    <div>
                        @include('partials.ui.status', ['status' => $paymentRequest->status])
                    </div>
                </div>
                <div class="finance-payment-request-meta">
                    <div><strong>Cargo:</strong> {{ $paymentRequest->charge?->concept ?? 'N/D' }}</div>
                    <div><strong>Monto solicitado:</strong>
                        @if(($paymentRequest->currency ?? 'USD') === 'VES')
                            {{ \App\Support\MoneyFormat::ves((float) ($paymentRequest->original_amount ?? $paymentRequest->amount)) }}
                            → {{ \App\Support\MoneyFormat::usd((float) $paymentRequest->amount) }}
                            @if($paymentRequest->exchange_rate)
                                <span class="table-sub">(tasa {{ MoneyFormat::rate((float) $paymentRequest->exchange_rate) }})</span>
                            @endif
                        @else
                            {{ \App\Support\MoneyFormat::usd((float) $paymentRequest->amount) }}
                        @endif
                    </div>
                    <div><strong>Referencia:</strong> {{ $paymentRequest->reference ?: 'Sin referencia' }}</div>
                    @if($paymentRequest->status === \App\Models\ChargePaymentRequest::STATUS_PENDING_VALIDATION
                        && ($duplicatePayment = \App\Services\PaymentRegistrationService::findDuplicateReference($paymentRequest->reference, $paymentRequest->payment_method_id)))
                        <div class="field-error">Atención: esta referencia ya está en el pago {{ $duplicatePayment->receipt?->receipt_number ?? '#'.$duplicatePayment->id }} del {{ $duplicatePayment->paid_at?->format('d/m/Y') }}. Revisa que no sea el mismo comprobante.</div>
                    @endif
                    @if($paymentRequest->payment_method)
                        <div><strong>Método declarado:</strong> {{ $paymentRequest->payment_method }}</div>
                    @endif
                </div>
                <div class="finance-payment-request-panes">
                    <div class="finance-payment-request-pane">
                        <h4>Comprobante</h4>
                        <button type="button" class="btn secondary finance-proof-open-btn" onclick="document.getElementById('finance-proof-dialog-{{ $paymentRequest->id }}').showModal()">
                            Ver comprobante
                        </button>
                        <div class="table-sub">{{ $paymentRequest->proof_original_name ?: 'Archivo adjunto' }}</div>
                    </div>
                    <div class="finance-payment-request-pane finance-payment-request-pane-actions">
                        <h4>Decisión</h4>
                        @if($paymentRequest->status === \App\Models\ChargePaymentRequest::STATUS_PENDING_VALIDATION)
                            <div class="finance-payment-request-actions-row">
                                <form method="POST" action="{{ route('finance.payment-requests.review', $paymentRequest) }}" class="finance-payment-action-form" data-guard-submit>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="approve">
                                    <label class="finance-reject-label" for="approve-paid-at-{{ $paymentRequest->id }}">Fecha del pago</label>
                                    <input type="date" id="approve-paid-at-{{ $paymentRequest->id }}" name="paid_at" required max="{{ now()->toDateString() }}" value="{{ ($paymentRequest->submitted_at ?? now())->toDateString() }}">
                                    <button class="btn" type="submit" data-submit-busy-label="Aprobando…">Aprobar</button>
                                </form>
                                <form method="POST" action="{{ route('finance.payment-requests.review', $paymentRequest) }}" class="finance-payment-action-form finance-payment-reject-form" data-guard-submit>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="reject">
                                    <label class="finance-reject-label" for="reject-reason-{{ $paymentRequest->id }}">Motivo de rechazo</label>
                                    <input id="reject-reason-{{ $paymentRequest->id }}" name="rejection_reason" required maxlength="500" list="reject-reasons" placeholder="Elige o escribe el motivo (se le envía a la familia)">
                                    <button class="btn secondary" type="submit" data-submit-busy-label="Rechazando…">Rechazar</button>
                                </form>
                            </div>
                        @else
                            <div class="table-sub">{{ $paymentRequest->status === \App\Models\ChargePaymentRequest::STATUS_APPROVED ? 'Esta solicitud ya fue aprobada y aplicada.' : 'Esta solicitud fue rechazada.' }}</div>
                            @if($paymentRequest->status === \App\Models\ChargePaymentRequest::STATUS_REJECTED && $paymentRequest->rejection_reason)
                                <div class="finance-payment-reject-note"><strong>Motivo registrado:</strong> {{ $paymentRequest->rejection_reason }}</div>
                            @endif
                        @endif
                    </div>
                </div>
            </article>
            <dialog id="finance-proof-dialog-{{ $paymentRequest->id }}" class="finance-proof-dialog">
                <div class="finance-proof-dialog-head">
                    <h3 class="finance-proof-dialog-title">Comprobante · {{ $paymentRequest->student?->full_name ?? 'Alumno' }}</h3>
                    <button type="button" class="charge-pay-dialog-close" onclick="document.getElementById('finance-proof-dialog-{{ $paymentRequest->id }}').close()" aria-label="Cerrar">&times;</button>
                </div>
                <div class="finance-proof-dialog-body">
                    @if($isImage)
                        <img src="{{ $proofUrl }}" alt="Comprobante de pago" class="finance-proof-img">
                    @elseif($isPdf)
                        <iframe src="{{ $proofUrl }}" title="Comprobante PDF" class="finance-proof-iframe"></iframe>
                    @else
                        <p class="finance-proof-fallback">No hay vista previa para este tipo de archivo.</p>
                        <p><a class="btn secondary" href="{{ $proofUrl }}" target="_blank" rel="noopener">Abrir o descargar archivo</a></p>
                    @endif
                </div>
                <div class="finance-proof-dialog-footer">
                    <a class="btn secondary" href="{{ $proofUrl }}" target="_blank" rel="noopener">Abrir en pestaña nueva</a>
                    <button type="button" class="btn" onclick="document.getElementById('finance-proof-dialog-{{ $paymentRequest->id }}').close()">Cerrar</button>
                </div>
            </dialog>
        @empty
            <div class="empty-state-inline">No hay comprobantes en este estado.</div>
        @endforelse
    </div>
    @if($paymentRequests->hasPages())
        {{ $paymentRequests->links() }}
    @endif
    <datalist id="reject-reasons">
        <option value="El monto del comprobante no coincide con el cargo.">
        <option value="No se encontró la transferencia en la cuenta.">
        <option value="El comprobante no se lee bien. Envíalo de nuevo, por favor.">
        <option value="La referencia ya fue usada en otro pago.">
        <option value="El comprobante corresponde a otro alumno o a otro cargo.">
    </datalist>
</div>
@endif

{{-- Registrar pago / Nuevo cargo: primero se elige el alumno; el formulario carga solo sus cargos. --}}
@unless($focusStudent)
    <x-ui.modal id="finance-pick-student" title="¿Para qué alumno?" subtitle="Busca por nombre, cédula o representante." size="md">
        @include('partials.layout.student-search', ['searchId' => 'finance-pick', 'resultUrl' => route('finance.index', ['student_id' => '__ID__', 'open' => '__INTENT__', 'tab' => $tab])])
    </x-ui.modal>
@else
    <x-ui.modal id="finance-charge-modal" title="Nuevo cargo" subtitle="A nombre de {{ $focusStudent->full_name }}" size="md"
        :data-open-on-load="($reopen === 'finance-charge-modal' || ($openModal === 'charge' && ! session('success'))) ? '1' : null">
        @include('partials.finance.register-charge-form', [
            'formAction' => route('finance.charges.store'),
            'formId' => 'finance-charge-form',
            'lockStudent' => true,
            'studentId' => $focusStudent->id,
            'students' => $students,
            'enrollments' => $enrollments,
            'focusStudentId' => $focusStudentId,
        ])
    </x-ui.modal>
    <x-ui.modal id="finance-payment-modal" title="Registrar pago" subtitle="A nombre de {{ $focusStudent->full_name }}" size="lg"
        :data-open-on-load="($reopen === 'finance-payment-modal' || ($openModal === 'payment' && ! session('success'))) ? '1' : null">
        @include('partials.finance.register-payment-form', [
            'formAction' => route('finance.payments.store'),
            'formId' => 'finance-payment-form',
            'prefix' => 'finance-payment',
            'lockStudent' => true,
            'studentId' => $focusStudent->id,
            'students' => $students,
            'charges' => $payableCharges,
            'paymentMethods' => $paymentMethods,
            'bcvRate' => $bcvRate,
            'bcvEurRate' => $bcvEurRate,
            'focusStudentId' => $focusStudentId,
        ])
    </x-ui.modal>
@endunless
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('dialog.finance-proof-dialog').forEach(function (dlg) {
            dlg.addEventListener('click', function (e) {
                if (e.target === dlg) {
                    dlg.close();
                }
            });
        });
    });
</script>
@endpush
