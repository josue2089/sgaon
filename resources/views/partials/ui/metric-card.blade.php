<div class="metric-card {{ $tone ?? 'metric-blue' }}">
    @if(!empty($icon))
        <div class="metric-icon">{{ $icon }}</div>
    @endif
    <div class="metric-label">{{ $label }}</div>
    <div class="metric-value">{{ $value }}</div>
    @if(!empty($subtitle))
        <div class="metric-subtitle">{{ $subtitle }}</div>
    @endif
</div>
