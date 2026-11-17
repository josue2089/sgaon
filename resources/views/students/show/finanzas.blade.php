{{-- Ficha del alumno: pestaña "finanzas". Separada de students/show en #454. --}}
@php use App\Support\MoneyFormat; @endphp
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
                        <th scope="col">Fecha</th>
                        <th scope="col">Monto</th>
                        <th scope="col">Método</th>
                        <th scope="col">Recibo</th>
                        <th scope="col"><span class="sr-only">Acciones</span></th>
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
                        <th scope="col">Concepto</th>
                        <th scope="col">Curso</th>
                        <th scope="col">Monto</th>
                        <th scope="col">Saldo</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="sr-only">Acciones</span></th>
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
