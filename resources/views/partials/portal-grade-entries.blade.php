{{-- Expects $gradeEntries iterable of GradeEntry models with evaluationSet.course --}}
<div class="grades-portal-list">
    @forelse($gradeEntries as $entry)
        @php($evalSet = $entry->evaluationSet)
        @php($gcourse = $evalSet?->course)
        <article class="grades-portal-entry">
            <div class="grades-portal-entry-head">
                <div class="table-title">{{ $gcourse?->name ?? 'Curso' }}</div>
                <div class="table-sub">{{ $evalSet?->evaluated_on?->format('d/m/Y') ?? '' }} @if($evalSet?->title)· {{ $evalSet->title }}@endif</div>
            </div>
            <div class="grades-portal-rubrics">
                @foreach(\App\Support\GradeRubric::SKILL_KEYS as $skill)
                    @php($rating = $entry->ratingForSkill($skill))
                    <span class="grades-portal-chip">
                        <span>{{ \App\Support\GradeRubric::SKILL_LABELS_ES[$skill] }}</span>
                        @include('partials.ui.status-badge', ['tone' => \App\Support\GradeRubric::ratingTone($rating), 'text' => \App\Support\GradeRubric::RATING_LABELS_ES[$rating] ?? $rating])
                    </span>
                @endforeach
            </div>
            @if($entry->observations)
                <div class="grades-portal-obs">{{ $entry->observations }}</div>
            @endif
        </article>
    @empty
        <div class="empty-state-inline">Sin evaluaciones publicadas todavía.</div>
    @endforelse
</div>
