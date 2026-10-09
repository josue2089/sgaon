@php($reportLinks = \App\Support\Navigation::reportLinks(auth()->user()))
@if(count($reportLinks) > 1)
    <nav class="section-tabs" aria-label="Reportes">
        @foreach($reportLinks as $report)
            <a href="{{ $report['url'] }}" @class(['section-tab', 'is-active' => $report['active']]) aria-current="{{ $report['active'] ? 'page' : 'false' }}">{{ $report['label'] }}</a>
        @endforeach
    </nav>
@endif
