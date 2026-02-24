<div class="soft-kpi {{ $class ?? '' }}">
    @if(!empty($iconName))
        <div class="soft-kpi-icon">
            @include('partials.ui.icon', ['name' => $iconName])
        </div>
    @elseif(!empty($icon))
        <div class="soft-kpi-icon">{{ $icon }}</div>
    @endif
    <div class="label">{{ $label }}</div>
    <div class="value {{ $valueClass ?? '' }}">{{ $value }}</div>
    @if(!empty($subtitle))
        <div class="entity-sub">{{ $subtitle }}</div>
    @endif
</div>
