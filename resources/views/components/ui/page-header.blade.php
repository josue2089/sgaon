@props(['title', 'subtitle' => null])
<div {{ $attributes->class(['module-head']) }}>
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="form-actions">{{ $actions }}</div>
    @endisset
</div>
