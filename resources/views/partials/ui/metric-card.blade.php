<div class="metric-card {{ $tone ?? 'metric-blue' }}">
    @if(!empty($iconName))
        <div class="metric-icon">
            @include('partials.ui.icon', ['name' => $iconName])
        </div>
    @elseif(!empty($icon))
        <div class="metric-icon">{{ $icon }}</div>
    @endif
    <div class="metric-label">{{ $label }}</div>
    <div class="metric-value">{{ $value }}</div>
    @if(!empty($subtitle))
        <div class="metric-subtitle">{{ $subtitle }}</div>
    @endif
</div>
