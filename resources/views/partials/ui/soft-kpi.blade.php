<div class="soft-kpi {{ $class ?? '' }}">
    @if(!empty($icon))
        <div class="soft-kpi-icon">{{ $icon }}</div>
    @endif
    <div class="label">{{ $label }}</div>
    <div class="value {{ $valueClass ?? '' }}">{{ $value }}</div>
    @if(!empty($subtitle))
        <div class="entity-sub">{{ $subtitle }}</div>
    @endif
</div>
