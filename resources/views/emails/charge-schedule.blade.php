@php
    use App\Support\MoneyFormat;

    $student = $enrollment->student;
    $course = $enrollment->group?->course;
    $activity = $course?->program?->name ?? $course?->name;
    $total = $charges->sum('amount');
    $currency = $charges->first()?->currency;
@endphp
<div style="font-family:Arial,sans-serif;color:#17305c;line-height:1.5;">
    <h2 style="margin:0 0 12px;">Calendario de cuotas</h2>
    <p>Hola, {{ $student?->full_name }} quedó inscrito(a) en la actividad <strong>{{ $activity }}</strong>. Estas son sus cuotas mensuales:</p>
    <table style="border-collapse:collapse;width:100%;max-width:520px;">
        <thead>
        <tr>
            <th style="text-align:left;border-bottom:2px solid #d8e1f0;padding:6px;">Cuota</th>
            <th style="text-align:right;border-bottom:2px solid #d8e1f0;padding:6px;">Monto</th>
            <th style="text-align:right;border-bottom:2px solid #d8e1f0;padding:6px;">Vence</th>
        </tr>
        </thead>
        <tbody>
        @foreach($charges as $charge)
            <tr>
                <td style="border-bottom:1px solid #eef2f8;padding:6px;">{{ $charge->concept }}</td>
                <td style="border-bottom:1px solid #eef2f8;padding:6px;text-align:right;">{{ MoneyFormat::formatLedgerAmount((float) $charge->amount, $charge->currency) }}</td>
                <td style="border-bottom:1px solid #eef2f8;padding:6px;text-align:right;">{{ $charge->due_date?->format('d/m/Y') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td style="padding:6px;"><strong>Total</strong></td>
            <td style="padding:6px;text-align:right;"><strong>{{ MoneyFormat::formatLedgerAmount((float) $total, $currency) }}</strong></td>
            <td></td>
        </tr>
        </tfoot>
    </table>
    <p>Los pagos se realizan dentro de los primeros cinco (5) días de cada mes. La inasistencia a las clases no exonera del pago de la mensualidad.</p>
    <p><a href="{{ route('portal.student') }}" style="display:inline-block;padding:10px 14px;background:#0a1e5e;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Ir al portal</a></p>
</div>
