{{-- Cursos del siguiente nivel disponibles para inscribirse. $renewRoute arma la URL con el curso destino. #442 --}}
<div class="makeup-student-list">
    @forelse($offers as $offer)
        <article class="makeup-student-card">
            <div class="makeup-student-head">
                <div>
                    <div class="table-title">{{ $offer['target_course']->name }}</div>
                    <div class="table-sub">Desde {{ $offer['source_course']->name }}</div>
                </div>
                <div>
                    @if($offer['already_enrolled'])
                        @include('partials.ui.status-badge', ['tone' => 'ok', 'text' => 'Inscrito'])
                    @elseif($offer['eligible'])
                        @include('partials.ui.status-badge', ['tone' => 'info', 'text' => 'Disponible'])
                    @else
                        @include('partials.ui.status-badge', ['tone' => 'danger', 'text' => 'No elegible'])
                    @endif
                </div>
            </div>
            <div class="makeup-student-meta">
                <div><strong>Inicio:</strong> {{ $offer['target_course']->start_date?->format('d/m/Y') ?? 'N/D' }}</div>
                <div><strong>Horario:</strong> {{ $offer['target_course']->scheduleTemplate?->display_label ?? 'N/D' }}</div>
            </div>
            @if(! $offer['already_enrolled'] && $offer['eligible'])
                <form method="POST" action="{{ $renewRoute($offer['target_course']) }}" data-guard-submit data-confirm="Se inscribirá en {{ $offer['target_course']->name }} y se generará la mensualidad. ¿Continuar?">
                    @csrf
                    <button class="btn secondary" type="submit">{{ $buttonLabel }}</button>
                </form>
            @elseif(! $offer['eligible'])
                <div class="table-sub">{{ $offer['pending_grade'] ? 'Podrás inscribirte cuando se cargue la evaluación final.' : 'La evaluación final está en revisión (Need Support). Consulta con la administración.' }}</div>
            @endif
        </article>
    @empty
        <div class="empty-state-inline">Aún no hay cursos de renovación habilitados.</div>
    @endforelse
</div>
