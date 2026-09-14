@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">💰 Tarifa mensual</h1>
        <p class="page-subtitle">Tarifa base usada por el reporte de <strong>Proyección por sede</strong>. No modifica cargos ya emitidos.</p>
    </div>
    <a class="btn secondary" href="{{ route('reports.campus-projection') }}">Ver reporte</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('settings.billing.update') }}" class="stack-sm" data-guard-submit>
        @csrf
        @method('PUT')
        <div>
            <label for="fee_usd">Tarifa mensual por inscripción activa</label>
            <div style="display:flex; align-items:center; gap:0.5rem; max-width:280px;">
                <span style="font-weight:700;">$</span>
                <input id="fee_usd" name="fee_usd" type="number" step="0.01" min="0" max="10000"
                       value="{{ old('fee_usd', number_format($fee, 2, '.', '')) }}" required>
                <span class="page-subtitle" style="margin:0;">USD / mes</span>
            </div>
            @error('fee_usd')<div class="form-error">{{ $message }}</div>@enderror
            <p class="page-subtitle" style="margin-top:0.5rem;">
                Cada inscripción activa suma esta tarifa a la proyección mensual de su sede.
                Un alumno con dos inscripciones cuenta como dos mensualidades.
            </p>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit" data-submit-busy-label="Guardando…">Guardar tarifa</button>
        </div>
    </form>
</div>
@endsection
