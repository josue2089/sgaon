@php
    $toneClass = match ($tone ?? 'info') {
        'ok' => 'badge-ok',
        'warn' => 'badge-warn',
        'danger' => 'badge-danger',
        'level' => 'badge-level',
        default => 'badge-info',
    };
@endphp
<span class="badge-pill {{ $toneClass }}"@if(!empty($title)) title="{{ $title }}"@endif>{{ $text }}</span>
