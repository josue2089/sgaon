@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Instrucciones de pago</h1>
        <p class="page-subtitle">Mensaje global para recuperativas visible en el portal del alumno.</p>
    </div>
</div>

<div class="card">
    <form method="POST" action="{{ route('settings.makeup-payment-instructions.update') }}" class="stack-sm">
        @csrf
        @method('PUT')
        <div>
            <label for="instructions">Contenido</label>
            <textarea id="instructions" name="instructions" rows="10" placeholder="Ejemplo: Transferir a Banco X, cuenta 0102..., enviar comprobante con referencia y titular.">{{ old('instructions', $instructions) }}</textarea>
        </div>
        <button class="btn" type="submit">Guardar instrucciones</button>
    </form>
</div>
@endsection
