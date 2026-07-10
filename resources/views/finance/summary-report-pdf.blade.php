<!doctype html>
<html lang="es">
<head>
@php use App\Support\MoneyFormat; @endphp
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #172554; margin: 24px; }
        .header { border-bottom: 2px solid #d8e1f0; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { width: 100%; }
        .brand td { vertical-align: middle; }
        .brand-logo img { width: 68px; height: auto; }
        .brand-meta { text-align: right; }
        h1 { margin: 0 0 4px; font-size: 18px; }
        .subtitle { margin: 0; font-size: 10px; color: #64748b; }
        .filters { margin: 10px 0 14px; }
        .filters span { display: inline-block; margin-right: 14px; }
        .summary-grid { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 12px; }
        .summary-grid td { width: 25%; vertical-align: top; border: 1px solid #d8e1f0; border-radius: 6px; padding: 8px; }
        .summary-label { font-size: 8px; text-transform: uppercase; color: #64748b; margin-bottom: 3px; }
        .summary-value { font-size: 12px; font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #d8e1f0; padding: 5px 4px; text-align: left; vertical-align: top; }
        table.data th { background: #eef4ff; font-size: 8px; text-transform: uppercase; }
        .empty { color: #64748b; font-style: italic; padding: 8px 0; }
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
                    <div style="font-size: 14px; font-weight: 700;">ON English</div>
                    <div class="subtitle">Academy Portal · Reporte financiero</div>
                    <div class="subtitle">Generado: {{ $generatedAt->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
        <h1>{{ $title }}</h1>
    </div>

    <div class="filters">
        <span><strong>Desde:</strong> {{ $filters['start_date'] ?? 'Sin filtro' }}</span>
        <span><strong>Hasta:</strong> {{ $filters['end_date'] ?? 'Sin filtro' }}</span>
        <span><strong>Moneda:</strong> {{ $filters['currency'] ?? 'N/D' }}</span>
        <span><strong>Sede:</strong> {{ $filters['campus'] ?? 'Todas' }}</span>
    </div>

    @if(isset($summary['total_invoiced']))
        <table class="summary-grid">
            <tr>
                <td>
                    <div class="summary-label">Cargos creados</div>
                    <div class="summary-value">{{ MoneyFormat::number((float) ($summary['total_invoiced'] ?? 0)) }} {{ $summary['currency'] ?? '' }}</div>
                </td>
                <td>
                    <div class="summary-label">Cobros realizados</div>
                    <div class="summary-value">{{ MoneyFormat::number((float) ($summary['total_collected'] ?? 0)) }} {{ $summary['currency'] ?? '' }}</div>
                </td>
                <td>
                    <div class="summary-label">Pendiente por cobrar</div>
                    <div class="summary-value">{{ MoneyFormat::number((float) ($summary['total_outstanding'] ?? 0)) }} {{ $summary['currency'] ?? '' }}</div>
                </td>
                <td>
                    <div class="summary-label">Registros</div>
                    <div class="summary-value">{{ count($rows) }}</div>
                </td>
            </tr>
        </table>
    @endif

    @if(count($rows) > 0)
        <table class="data">
            <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No hay registros para los filtros seleccionados.</div>
    @endif
</body>
</html>
