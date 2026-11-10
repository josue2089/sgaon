{{-- Ruta de aprendizaje del alumno con sus equivalencias CEFR/GSE. #473 --}}
@if($path)
    @php($stateText = ['done' => 'Aprobado', 'current' => 'Cursando', 'upcoming' => 'Próximo'])
    @php($stateTone = ['done' => 'ok', 'current' => 'info', 'upcoming' => 'level'])
    <div class="card" id="learning-path">
        <div class="section-head section-head-tight">
            <h3 class="section-title section-title-md">Ruta de aprendizaje</h3>
            <div class="entity-sub">{{ $path['program']->name }} · alineado con CEFR y GSE</div>
        </div>
        <div class="summary-grid student-summary-grid">
            <div class="card summary-card">
                <div class="summary-label">Nivel actual</div>
                <div class="summary-value">{{ $path['level']->name }}</div>
                <div class="table-sub">{{ $path['level']->cefrSummary() ?? 'Nivel inicial' }}</div>
            </div>
            <div class="card summary-card">
                <div class="summary-label">Avance del curso</div>
                <div class="summary-value">{{ $path['sessions_done'] }}/{{ $path['sessions_total'] }}</div>
                <div class="table-sub">Clases dadas</div>
            </div>
            <div class="card summary-card">
                <div class="summary-label">Siguiente nivel</div>
                <div class="summary-value">{{ $path['next']?->name ?? '—' }}</div>
                <div class="table-sub">{{ $path['next']?->cefrSummary() ?? ($path['next'] ? '' : 'Último nivel del programa') }}</div>
            </div>
        </div>

        @if($path['adult_transition'])
            <div class="flash info">
                Al tener 14 años o más, el siguiente paso es el programa de adultos: <strong>{{ $path['adult_transition']->name }}</strong>
                ({{ $path['adult_transition']->cefr_label }}), equivalente a {{ $path['next']->name }}. Consulta con la sede para la inscripción.
            </div>
        @endif

        <details>
            <summary>Ver la ruta completa del programa ({{ count($path['levels']) }} niveles)</summary>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Nivel</th><th scope="col">CEFR / GSE</th><th scope="col">Estado</th></tr></thead>
                    <tbody>
                    @foreach($path['levels'] as $item)
                        <tr @if($item['state'] === 'current') aria-current="step" @endif>
                            <td>{{ $item['level']->name }}</td>
                            <td>
                                {{ $item['level']->cefr_label ?: '—' }}
                                @if($item['level']->gse_min && $item['level']->gse_max)<div class="table-sub">GSE {{ $item['level']->gse_min }}–{{ $item['level']->gse_max }}</div>@endif
                            </td>
                            <td>@include('partials.ui.status-badge', ['tone' => $stateTone[$item['state']], 'text' => $stateText[$item['state']]])</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>
@endif
