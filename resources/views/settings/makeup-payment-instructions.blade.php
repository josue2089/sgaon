@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Instrucciones de pago</h1>
        <p class="page-subtitle">Mensaje global para recuperativas visible en el portal del alumno.</p>
    </div>
</div>
@include('partials.layout.config-tabs')

<div class="card">
    <form method="POST" action="{{ route('settings.makeup-payment-instructions.update') }}" class="stack-sm">
        @csrf
        @method('PUT')
        <div>
            <label for="instructions">Contenido</label>
            <textarea id="instructions" name="instructions" rows="10" placeholder="Ejemplo: Transferir a Banco X, cuenta 0102..., enviar comprobante con referencia y titular.">{{ old('instructions', $instructions) }}</textarea>
        </div>
        <div>
            <label for="due_days">Días para pagar la recuperativa</label>
            <input id="due_days" type="number" name="due_days" min="0" max="60" required value="{{ old('due_days', $dueDays) }}">
            <div class="table-sub">El cargo de $10 vence estos días después de la inasistencia. Antes de esa fecha no cuenta como mora.</div>
            @error('due_days')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button class="btn" type="submit">Guardar</button>
    </form>
</div>
@endsection
