@extends('layouts.app')
@section('content')
<div class="card">
    <div class="module-head">
        <div>
            <h1 class="page-title">Reporte de pagos / CxC 💰</h1>
            <p class="page-subtitle">Resumen de cargos y monto efectivamente pagado</p>
        </div>
    </div>
    <form method="GET" action="{{ route('reports.payments') }}">
        <div class="grid-2">
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">Todos</option>
                    <option value="pending" @selected(request('status') === 'pending')>pending</option>
                    <option value="partial" @selected(request('status') === 'partial')>partial</option>
                    <option value="paid" @selected(request('status') === 'paid')>paid</option>
                    <option value="overdue" @selected(request('status') === 'overdue')>overdue</option>
                </select>
            </div>
            <div>
                <label>Período</label>
                <select name="period_id">
                    <option value="">Todos</option>
                    @foreach(($periods ?? collect()) as $period)
                        <option value="{{ $period->id }}" @selected((string) request('period_id') === (string) $period->id)>{{ $period->code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Tipo de cargo</label>
                <select name="charge_type">
                    <option value="">Todos</option>
                    <option value="tuition" @selected(request('charge_type') === 'tuition')>Mensualidad</option>
                    <option value="materials" @selected(request('charge_type') === 'materials')>Materiales</option>
                    <option value="registration" @selected(request('charge_type') === 'registration')>Inscripción</option>
                    <option value="makeup" @selected(request('charge_type') === 'makeup')>Recuperación</option>
                    <option value="other" @selected(request('charge_type') === 'other')>Otro</option>
                </select>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="only_overdue" value="1" @checked(request()->boolean('only_overdue')) style="width:auto;margin-right:.4rem;">
                    Solo en mora
                </label>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn secondary" href="{{ route('reports.payments', array_merge(request()->query(), ['export' => 'csv'])) }}">Exportar CxC CSV</a>
            <a class="btn secondary" href="{{ route('reports.payments', array_merge(request()->query(), ['export' => 'payments_detail_csv'])) }}">Exportar pagos CSV</a>
        </div>
    </form>
    <div class="form-actions">
        <form method="POST" action="{{ route('reports.exports.queue', array_merge(request()->query(), ['type' => 'payments'])) }}">
            @csrf
            <input type="hidden" name="type" value="payments">
            <button class="btn secondary" type="submit">Exportar en segundo plano</button>
        </form>
    </div>
    <div class="form-actions">
        <form method="POST" action="{{ route('reports.presets.store', request()->query()) }}">
            @csrf
            <input type="hidden" name="route_name" value="reports.payments">
            <input type="text" name="name" placeholder="Nombre del preset" style="max-width:220px;">
            <button class="btn secondary" type="submit">Guardar preset</button>
        </form>
    </div>
    @if(($presets ?? collect())->count() > 0)
        <div class="stack-sm">
            @foreach($presets as $preset)
                <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
                    <a class="btn secondary" href="{{ route('reports.payments', $preset->filters ?? []) }}">{{ $preset->name }}</a>
                    <form method="POST" action="{{ route('reports.presets.destroy', $preset) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn secondary" type="submit">Eliminar</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
    @if(($exports ?? collect())->count() > 0)
        <div class="stack-sm" style="margin-top:.8rem;">
            @foreach($exports as $export)
                <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
                    @include('partials.ui.status-badge', ['tone' => $export->status === 'done' ? 'ok' : ($export->status === 'failed' ? 'danger' : 'warn'), 'text' => strtoupper($export->status)])
                    <span class="entity-sub">{{ $export->created_at?->format('Y-m-d H:i') }}</span>
                    @if($export->status === 'done')
                        <a class="btn secondary" href="{{ route('reports.exports.download', $export) }}">Descargar</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="card">
    <table>
        <thead><tr><th>Alumno</th><th>Curso</th><th>Grupo</th><th>Período</th><th>Tipo</th><th>Concepto</th><th>Monto</th><th>Status</th><th>Pagado</th><th>Saldo</th></tr></thead>
        <tbody>
        @forelse($charges as $charge)
            @php
                $paidTotal = \App\Support\FinanceReconcile::paidTotalForCharge($charge);
                $balance = \App\Support\FinanceReconcile::outstandingForCharge($charge);
            @endphp
            <tr>
                <td>{{ $charge->student->full_name ?? '' }}</td>
                <td>{{ $charge->course->name ?? 'Sin curso' }}</td>
                <td>{{ $charge->group->name ?? 'Sin grupo' }}</td>
                <td>{{ $charge->period->code ?? ($charge->billing_period_label ?: 'Sin período') }}</td>
                <td>{{ $charge->charge_type ?: 'N/D' }}</td>
                <td>{{ $charge->concept }}</td>
                <td>${{ number_format($charge->amount,2) }}</td>
                <td><span class="status-pill {{ $charge->status === 'paid' ? 'success' : ($charge->status === 'overdue' ? 'danger' : 'warn') }}">{{ $charge->status }}</span></td>
                <td>${{ number_format($paidTotal,2) }}</td>
                <td>${{ number_format($balance,2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10">
                    <div class="empty-state-inline">No hay cargos registrados para reportar.</div>
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
    <h3 class="section-title section-title-sm">Desglose de pagos recibidos</h3>
    <table>
        <thead>
        <tr>
            <th>Alumno</th>
            <th>Fecha</th>
            <th>Moneda</th>
            <th>Monto original</th>
            <th>Tasa</th>
            <th>USD aplicado</th>
            <th>Método</th>
        </tr>
        </thead>
        <tbody>
        @forelse($recentPayments ?? [] as $payment)
            <tr>
                <td>{{ $payment->student->full_name ?? '' }}</td>
                <td>{{ $payment->paid_at?->format('Y-m-d') }}</td>
                <td>{{ $payment->currency ?? 'USD' }}</td>
                <td>
                    @if(($payment->currency ?? 'USD') === 'VES')
                        {{ \App\Support\MoneyFormat::ves((float) ($payment->original_amount ?? $payment->amount)) }}
                    @else
                        {{ \App\Support\MoneyFormat::usd((float) ($payment->original_amount ?? $payment->amount)) }}
                    @endif
                </td>
                <td>{{ $payment->exchange_rate ? number_format((float) $payment->exchange_rate, 4, ',', '.') : '—' }}</td>
                <td>{{ \App\Support\MoneyFormat::usd((float) $payment->amount) }}</td>
                <td>{{ $payment->method }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state-inline">No hay pagos registrados.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
