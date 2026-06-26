@extends('layouts.app')
@section('content')
@php
    $isEdit = isset($paymentMethod) && $paymentMethod?->exists;
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">{{ $isEdit ? 'Editar método de pago' : 'Nuevo método de pago' }}</h1>
        <p class="page-subtitle">Datos de cuenta que verá el usuario al elegir la moneda.</p>
    </div>
    <a class="btn secondary" href="{{ route('settings.payment-methods.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ $isEdit ? route('settings.payment-methods.update', $paymentMethod) : route('settings.payment-methods.store') }}" class="stack-sm">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <div class="grid-2">
            <div>
                <label for="currency">Moneda</label>
                <select id="currency" name="currency" required>
                    @foreach($currencyLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('currency', $paymentMethod->currency ?? \App\Models\PaymentMethod::CURRENCY_USD) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="method_type">Tipo</label>
                <select id="method_type" name="method_type" required>
                    @foreach($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('method_type', $paymentMethod->method_type ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label for="label">Nombre visible</label>
            <input id="label" name="label" value="{{ old('label', $paymentMethod->label ?? '') }}" required placeholder="Ej. Zelle ON English">
        </div>
        <div class="grid-2">
            <div>
                <label for="bank_name">Banco</label>
                <input id="bank_name" name="bank_name" value="{{ old('bank_name', $paymentMethod->bank_name ?? '') }}">
            </div>
            <div>
                <label for="account_holder">Titular</label>
                <input id="account_holder" name="account_holder" value="{{ old('account_holder', $paymentMethod->account_holder ?? '') }}">
            </div>
        </div>
        <div class="grid-2">
            <div>
                <label for="account_number">Número de cuenta</label>
                <input id="account_number" name="account_number" value="{{ old('account_number', $paymentMethod->account_number ?? '') }}">
            </div>
            <div>
                <label for="rif">RIF</label>
                <input id="rif" name="rif" value="{{ old('rif', $paymentMethod->rif ?? '') }}">
            </div>
        </div>
        <div class="grid-2">
            <div>
                <label for="phone">Teléfono</label>
                <input id="phone" name="phone" value="{{ old('phone', $paymentMethod->phone ?? '') }}">
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $paymentMethod->email ?? '') }}">
            </div>
        </div>
        <div>
            <label for="instructions">Instrucciones adicionales</label>
            <textarea id="instructions" name="instructions" rows="4">{{ old('instructions', $paymentMethod->instructions ?? '') }}</textarea>
        </div>
        <div class="grid-2">
            <div>
                <label for="sort_order">Orden</label>
                <input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $paymentMethod->sort_order ?? 0) }}">
            </div>
            <div>
                <label class="checkbox-inline">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $paymentMethod->is_active ?? true))>
                    Activo
                </label>
            </div>
        </div>
        <button class="btn" type="submit">{{ $isEdit ? 'Guardar cambios' : 'Crear método' }}</button>
    </form>
</div>
@endsection
