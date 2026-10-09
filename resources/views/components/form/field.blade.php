@props(['label', 'name' => null, 'required' => false, 'hint' => null])
{{-- Campo de formulario: etiqueta con marca de obligatorio, el control (slot), ayuda y error junto al campo. --}}
@php
    $errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null;
    $errorBag = $errors ?? view()->shared('errors') ?? new \Illuminate\Support\ViewErrorBag;
@endphp
<div {{ $attributes->class(['form-field', 'form-field--invalid' => $errorKey && $errorBag->has($errorKey)]) }}>
    <label @if($name) for="{{ $attributes->get('id', $name) }}" @endif>
        {{ $label }}@if($required)<span class="field-required" aria-hidden="true"> *</span>@endif
    </label>
    {{ $slot }}
    @if($hint)
        <div class="form-hint">{{ $hint }}</div>
    @endif
    @if($errorKey)
        @if($errorBag->has($errorKey))
            <div class="field-error" role="alert">{{ $errorBag->first($errorKey) }}</div>
        @endif
    @endif
</div>
