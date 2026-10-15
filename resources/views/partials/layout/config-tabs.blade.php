@php($configGroup = auth()->user() ? \App\Support\Navigation::currentConfigGroup(auth()->user()) : null)
@if($configGroup && count($configGroup['links']) > 1)
    <nav class="section-tabs" aria-label="Configuración: {{ $configGroup['heading'] }}">
        @foreach($configGroup['links'] as $link)
            <a href="{{ $link['url'] }}" @class(['section-tab', 'is-active' => $link['active']]) aria-current="{{ $link['active'] ? 'page' : 'false' }}">{{ $link['label'] }}</a>
        @endforeach
    </nav>
@endif
