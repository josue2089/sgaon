{{-- Reemplaza el formulario de pago cuando el cargo ya tiene comprobante en validación o es una recuperativa. #436 --}}
@if($charge->hasPaymentInValidation())
    @include('partials.ui.status-badge', ['tone' => 'warn', 'text' => 'En validación'])
    <div class="table-sub">Ya recibimos el comprobante. Te avisaremos al validarlo.</div>
@else
    <div class="table-sub">Esta recuperativa se paga desde la pestaña <strong>Recuperativas</strong>.</div>
@endif
