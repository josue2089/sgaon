@php use App\Support\MoneyFormat; @endphp
@extends('layouts.app')
@section('content')
@php
    $chargesTotal = $charges->sum('amount');
    $paymentsTotal = $payments->sum('amount');
    $overdueCount = $charges->where('status', 'overdue')->count();
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Financiero</h1>
        <p class="page-subtitle">
            Control de cargos, pagos y cobranza
            @if(($bcvRate['rate'] ?? 0) > 0)
                · Tasa BCV USD: Bs {{ MoneyFormat::rate($bcvRate['rate']) }}
            @endif
            @if(($bcvEurRate['rate'] ?? 0) > 0)
                · Tasa BCV EUR: Bs {{ MoneyFormat::rate($bcvEurRate['rate']) }}
            @endif
        </p>
    </div>
    <a class="btn secondary" href="{{ route('finance.index', array_merge(request()->query(), ['export' => 'mora_csv'])) }}">Exportar mora CSV</a>
    <a class="btn secondary" href="{{ route('finance.summary') }}">Resumen financiero</a>
    @if($focusStudentId)
        @include('partials.ui.status-badge', ['tone' => 'info', 'text' => 'Enfocado en alumno #'.$focusStudentId])
    @endif
</div>

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Cargos (página) USD', 'value' => MoneyFormat::usd($chargesTotal)])
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Pagos recientes USD', 'value' => MoneyFormat::usd($paymentsTotal)])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Cuentas en mora', 'value' => $overdueCount, 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Mora crítica (30+ días)', 'value' => $criticalOverdueCount, 'valueClass' => 'value-danger'])
</div>

<div class="grid-2">
    <div class="card">
        <h3 class="section-title section-title-sm">Nuevo cargo</h3>
        @include('partials.finance.register-charge-form', [
            'formAction' => route('finance.charges.store'),
            'formId' => 'finance-charge-form',
            'students' => $students,
            'enrollments' => $enrollments,
            'focusStudentId' => $focusStudentId,
        ])
    </div>

    <div class="card">
        <h3 class="section-title section-title-sm">Registrar pago</h3>
        @include('partials.finance.register-payment-form', [
            'formAction' => route('finance.payments.store'),
            'formId' => 'finance-payment-form',
            'prefix' => 'finance-payment',
            'students' => $students,
            'charges' => $charges,
            'paymentMethods' => $paymentMethods,
            'bcvRate' => $bcvRate,
            'bcvEurRate' => $bcvEurRate,
            'focusStudentId' => $focusStudentId,
        ])
    </div>
</div>

<div class="card finance-payment-requests-card">
    <div class="section-head section-head-tight">
        <h3 class="section-title section-title-sm">Solicitudes de pago por validar</h3>
        <div class="entity-sub">Revisa el comprobante en pantalla y define la acción sin tablas anchas</div>
    </div>
    <div class="finance-payment-request-list">
        @forelse($paymentRequests as $paymentRequest)
            @php
                $proofUrl = asset('storage/'.$paymentRequest->proof_path);
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
                        @include('partials.ui.status-badge', [
                            'tone' => $paymentRequest->status === \App\Models\ChargePaymentRequest::STATUS_APPROVED ? 'ok' : ($paymentRequest->status === \App\Models\ChargePaymentRequest::STATUS_REJECTED ? 'danger' : 'warn'),
                            'text' => ucfirst(str_replace('_', ' ', $paymentRequest->status)),
                        ])
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
                                <form method="POST" action="{{ route('finance.payment-requests.review', $paymentRequest) }}" class="finance-payment-action-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="approve">
                                    <button class="btn" type="submit">Aprobar</button>
                                </form>
                                <form method="POST" action="{{ route('finance.payment-requests.review', $paymentRequest) }}" class="finance-payment-action-form finance-payment-reject-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="reject">
                                    <label class="finance-reject-label" for="reject-reason-{{ $paymentRequest->id }}">Motivo de rechazo</label>
                                    <textarea id="reject-reason-{{ $paymentRequest->id }}" name="rejection_reason" rows="2" placeholder="Solo si rechazas — describe el problema del comprobante"></textarea>
                                    <button class="btn secondary" type="submit">Rechazar</button>
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
            <div class="empty-state-inline">No hay solicitudes de pago pendientes.</div>
        @endforelse
    </div>
</div>

<div class="card">
    <h3 class="section-title section-title-sm">Cuentas por cobrar</h3>
    <table>
        <thead>
        <tr><th>Alumno</th><th>Curso</th><th>Grupo</th><th>Periodo</th><th>Concepto</th><th>Monto</th><th>Pagado</th><th>Saldo</th><th>Mora</th><th>Status</th></tr>
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
                <td><span class="status-pill {{ $charge->status === 'paid' ? 'success' : ($charge->status === 'overdue' ? 'danger' : 'warn') }}">{{ $charge->status }}</span></td>
            </tr>
        @empty
            <tr>
                <td colspan="10">
                    <div class="empty-state-inline">No hay cargos para el contexto seleccionado.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    @if($charges->hasPages())
        {{ $charges->links() }}
    @endif
</div>

<div class="card">
    <h3 class="section-title section-title-sm">Pagos recientes / Recibos</h3>
    <table>
        <thead>
        <tr><th>Alumno</th><th>Moneda</th><th>Monto original</th><th>Monto aplicado</th><th>Tasa</th><th>Fecha</th><th>Recibo</th></tr>
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
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state-inline">Aún no hay pagos registrados.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
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
