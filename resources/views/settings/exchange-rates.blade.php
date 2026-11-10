@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Tasas BCV</h1>
        <p class="page-subtitle">Tasas oficiales con las que se convierten los pagos en bolívares. Se sincronizan solas cada día a las 5:40 a. m.</p>
    </div>
    <form method="POST" action="{{ route('settings.exchange-rates.sync') }}" data-guard-submit>
        @csrf
        <button class="btn" type="submit" data-submit-busy-label="Consultando al BCV…">Sincronizar ahora</button>
    </form>
</div>
@include('partials.layout.config-tabs')

<div class="grid-2">
    @foreach($current as $code => $rate)
        <div class="card">
            <div class="section-head section-head-tight">
                <h2 class="section-title section-title-md">{{ $code }} → Bs</h2>
                @if(! $rate)
                    @include('partials.ui.status-badge', ['tone' => 'danger', 'text' => 'Sin tasa'])
                @elseif($rate->isStale())
                    @include('partials.ui.status-badge', ['tone' => 'danger', 'text' => 'Desactualizada'])
                @else
                    @include('partials.ui.status-badge', ['tone' => 'ok', 'text' => 'Al día'])
                @endif
            </div>
            @if($rate)
                <div class="page-title">Bs {{ number_format((float) $rate->rate, 4, ',', '.') }}</div>
                <div class="detail-list">
                    <div><strong>Vigente desde:</strong> {{ $rate->effective_at->format('d/m/Y') }}</div>
                    <div><strong>Guardada:</strong> {{ $rate->captured_at?->format('d/m/Y h:i a') ?? '—' }}</div>
                    <div><strong>Fuente:</strong> {{ $rate->source === \App\Models\ExchangeRate::SOURCE_MANUAL ? 'Manual'.($rate->author ? ' ('.$rate->author->name.')' : '') : 'BCV' }}</div>
                </div>
            @else
                <p class="entity-sub">Todavía no hay ninguna tasa guardada.</p>
            @endif
        </div>
    @endforeach
</div>
<p class="entity-sub">Se considera desactualizada si su vigencia es anterior al último día hábil ({{ $previousBusinessDay->format('d/m/Y') }}). En ese caso aparece un aviso en “Hoy” y en el correo diario.</p>

@if($canSetManual)
    <div class="card">
        <h2 class="section-title section-title-md">Cargar tasa a mano</h2>
        <p class="entity-sub">Úsala solo si el BCV no responde. Mientras la API funcione, la tasa del día viene del BCV; la manual se usa como respaldo y para su fecha de vigencia.</p>
        <form method="POST" action="{{ route('settings.exchange-rates.manual') }}" class="stack-sm" data-guard-submit>
            @csrf
            <div class="grid-3">
            <div>
                <label for="currency">Moneda</label>
                <select id="currency" name="currency" required>
                    @foreach(['USD', 'EUR'] as $code)
                        <option value="{{ $code }}" @selected(old('currency') === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rate">Tasa (Bs por unidad)</label>
                <input id="rate" name="rate" type="number" step="0.0001" min="0.0001" value="{{ old('rate') }}" required inputmode="decimal">
                @error('rate')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="effective_at">Vigente desde</label>
                <input id="effective_at" name="effective_at" type="date" value="{{ old('effective_at', today()->toDateString()) }}" required>
                @error('effective_at')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            </div>
            <div>
                <label for="note">Motivo</label>
                <input id="note" name="note" type="text" maxlength="255" value="{{ old('note') }}" placeholder="Ej.: la página del BCV no carga desde la mañana" required>
                @error('note')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-actions">
                <button class="btn secondary" type="submit" data-submit-busy-label="Guardando…">Guardar tasa manual</button>
            </div>
        </form>
    </div>
@endif

<div class="card table-card">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Historial</h2>
        <form method="GET" action="{{ route('settings.exchange-rates.index') }}" class="fi-filter-bar">
            <select name="currency" aria-label="Moneda" onchange="this.form.submit()">
                <option value="">Todas las monedas</option>
                @foreach(['USD', 'EUR'] as $code)
                    <option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>
                @endforeach
            </select>
            <noscript><button class="btn secondary" type="submit">Filtrar</button></noscript>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th scope="col">Vigencia</th><th scope="col">Moneda</th><th scope="col">Tasa</th><th scope="col">Fuente</th><th scope="col">Guardada</th></tr></thead>
            <tbody>
            @forelse($rates as $rate)
                <tr>
                    <td>{{ $rate->effective_at->format('d/m/Y') }}</td>
                    <td>{{ $rate->currency }}</td>
                    <td>Bs {{ number_format((float) $rate->rate, 4, ',', '.') }}</td>
                    <td>
                        @if($rate->source === \App\Models\ExchangeRate::SOURCE_MANUAL)
                            Manual{{ $rate->author ? ' · '.$rate->author->name : '' }}
                            <div class="table-sub">{{ $rate->note }}</div>
                        @else
                            BCV
                        @endif
                    </td>
                    <td>{{ $rate->captured_at?->format('d/m/Y h:i a') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-inline">No hay tasas guardadas.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@if($rates->hasPages())<div class="card">{{ $rates->links() }}</div>@endif
@endsection
