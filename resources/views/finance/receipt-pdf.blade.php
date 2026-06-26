<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $receipt->receipt_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #172554;
            margin: 32px;
        }
        .header {
            margin-bottom: 24px;
            border-bottom: 2px solid #d8e1f0;
            padding-bottom: 18px;
        }
        .brand {
            width: 100%;
            margin-bottom: 18px;
        }
        .brand td {
            vertical-align: middle;
        }
        .brand-logo {
            width: 88px;
        }
        .brand-logo img {
            width: 78px;
            height: auto;
        }
        .brand-meta {
            text-align: right;
        }
        .title {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .subtitle {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }
        .grid {
            width: 100%;
            margin-bottom: 20px;
        }
        .grid td {
            width: 50%;
            vertical-align: top;
            padding-right: 16px;
        }
        .card {
            border: 1px solid #d8e1f0;
            border-radius: 12px;
            padding: 16px;
        }
        .section-title {
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 10px;
        }
        .row {
            margin-bottom: 7px;
        }
        .label {
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        th, td {
            border: 1px solid #d8e1f0;
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #eef4ff;
            font-size: 11px;
            text-transform: uppercase;
        }
        .amount {
            text-align: right;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="brand">
            <tr>
                <td class="brand-logo">
                    @if(!empty($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="ON English">
                    @endif
                </td>
                <td class="brand-meta">
                    <div style="font-size: 18px; font-weight: 700;">ON English</div>
                    <div class="subtitle">Academy Portal · Recibo de pago</div>
                </td>
            </tr>
        </table>
        <h1 class="title">Recibo {{ $receipt->receipt_number }}</h1>
        <p class="subtitle">Detalle exacto de cargos aplicados por este pago</p>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="card">
                    <div class="section-title">Datos del recibo</div>
                    <div class="row"><span class="label">Número:</span> {{ $receipt->receipt_number }}</div>
                    <div class="row"><span class="label">Alumno:</span> {{ $payment->student->full_name ?? 'N/D' }}</div>
                    <div class="row"><span class="label">Fecha:</span> {{ $receipt->issued_at?->format('d/m/Y') ?? 'N/D' }}</div>
                    <div class="row"><span class="label">Monto pagado (USD aplicado):</span> {{ \App\Support\MoneyFormat::usd((float) $payment->amount) }}</div>
                    @if(($payment->currency ?? 'USD') === 'VES')
                        <div class="row"><span class="label">Monto en Bs:</span> {{ \App\Support\MoneyFormat::ves((float) ($payment->original_amount ?? 0)) }}</div>
                        <div class="row"><span class="label">Tasa BCV:</span> {{ number_format((float) $payment->exchange_rate, 4, ',', '.') }}</div>
                    @endif
                    <div class="row"><span class="label">Método:</span> {{ $payment->method ?: 'Sin método' }}</div>
                    <div class="row"><span class="label">Referencia:</span> {{ $payment->reference ?: 'Sin referencia' }}</div>
                    <div class="row"><span class="label">Recibido por:</span> {{ $payment->receivedBy?->name ?? 'N/D' }}</div>
                </div>
            </td>
            <td>
                <div class="card">
                    <div class="section-title">Resumen de aplicación</div>
                    <div class="row"><span class="label">Cargos impactados:</span> {{ $allocations->count() }}</div>
                    <div class="row"><span class="label">Total aplicado:</span> ${{ number_format($allocations->sum(fn ($item) => (float) ($item->amount_applied ?? 0)), 2) }}</div>
                    <div class="row"><span class="label">Observación:</span> {{ $payment->notes ?: 'Sin observaciones' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Curso</th>
                <th>Grupo</th>
                <th>Período</th>
                <th class="amount">Monto aplicado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($allocations as $allocation)
                <tr>
                    <td>{{ $allocation->charge->concept ?? 'Cargo legacy' }}</td>
                    <td>{{ $allocation->charge->course->name ?? 'Sin curso' }}</td>
                    <td>{{ $allocation->charge->group->name ?? 'Sin grupo' }}</td>
                    <td>{{ $allocation->charge->period->code ?? ($allocation->charge->billing_period_label ?? 'Sin período') }}</td>
                    <td class="amount">${{ number_format((float) ($allocation->amount_applied ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
