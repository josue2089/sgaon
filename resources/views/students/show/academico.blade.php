{{-- Ficha del alumno: pestaña "academico". Separada de students/show en #454. --}}
<section class="portal-tab-panel student-panel" id="panel-academico" data-tab-panel="academico" role="tabpanel" aria-labelledby="tab-academico">
<div class="card" id="student-evaluations">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Evaluaciones por curso</h2>
        <div class="entity-sub">Histórico de rubros por informe</div>
    </div>
    @forelse($gradeEvaluationHistory as $entry)
        @php($evalSet = $entry->evaluationSet)
        @php($gcourse = $evalSet?->course)
        <article class="grades-student-entry">
            <div class="grades-student-entry-head">
                <div>
                    <div class="table-title">{{ $gcourse?->name ?? 'Curso' }}</div>
                    <div class="table-sub">{{ $evalSet?->evaluated_on?->format('d/m/Y') ?? '' }} @if($evalSet?->title)· {{ $evalSet->title }}@endif @if($evalSet && ! $evalSet->isPublished())· <strong>Borrador</strong>, no visible para la familia @endif</div>
                </div>
                @if($gcourse)
                    <a class="btn secondary" href="{{ route('courses.grades.index', $gcourse) }}">Evaluaciones del curso</a>
                @endif
            </div>
            <div class="grades-student-rubrics">
                @foreach(\App\Support\GradeRubric::SKILL_KEYS as $skill)
                    @php($rating = $entry->ratingForSkill($skill))
                    <div class="grades-student-rubric">
                        <span>{{ \App\Support\GradeRubric::SKILL_LABELS_ES[$skill] }}</span>
                        @include('partials.ui.status-badge', ['tone' => \App\Support\GradeRubric::ratingTone($rating), 'text' => \App\Support\GradeRubric::RATING_LABELS_ES[$rating] ?? 'Sin nota'])
                    </div>
                @endforeach
            </div>
            @if($entry->observations)
                <div class="grades-student-obs">{{ $entry->observations }}</div>
            @endif
        </article>
    @empty
        <div class="empty-state-inline">Sin evaluaciones registradas.</div>
    @endforelse
</div>

<div class="card table-card" id="student-academic-history">
    <div class="section-head">
        <h2 class="section-title section-title-md">Histórico de cursos</h2>
        <div class="entity-sub">{{ $courseHistory->count() }} inscripción(es)</div>
    </div>
    @if($courseHistory->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Curso</th>
                    <th>Nivel</th>
                    <th>Profesor</th>
                    <th>Período</th>
                    <th>Fechas</th>
                    <th>Asistencia</th>
                    <th>Estado</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                @forelse($courseHistory as $enrollment)
                    @php($course = optional($enrollment->group)->course)
                    @php($level = $course?->programLevel ?: $course?->courseLevel)
                    @php($attendanceRate = (int) $enrollment->attendance_records_count > 0
                        ? (int) round(($enrollment->present_attendance_count / $enrollment->attendance_records_count) * 100)
                        : null)
                    @php($levelLabel = $level
                        ? (($level->sort_order ?? $level->scale_position).'/'.($level->program_total ?? $level->scale_total).' - '.$level->name)
                        : ($course?->level?->name ?? 'N/D'))
                    <tr>
                        <td>
                            <div class="table-title">{{ $course?->name ?? 'N/D' }}</div>
                            <div class="table-sub">{{ $course?->code ?: 'Sin codigo' }}</div>
                        </td>
                        <td>{{ $levelLabel }}</td>
                        <td>{{ $course?->teacher?->full_name ?? 'N/D' }}</td>
                        <td>{{ $course?->period?->code ?? 'N/D' }}</td>
                        <td>{{ $course?->start_date?->format('d/m/Y') ?? 'N/D' }} - {{ $course?->end_date?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td>{{ is_null($attendanceRate) ? 'N/D' : $attendanceRate.'%' }}</td>
                        <td>@include('partials.ui.status', ['status' => $enrollment->status, 'domain' => 'enrollment'])</td>
                        <td class="table-actions">
                            @if($enrollment->group_id)<a href="{{ route('students.logbook', [$student, $enrollment]) }}">Bitácora</a>@endif
                            @include('partials.enrollments.change-actions', ['enrollment' => $enrollment->setRelation('student', $student)])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty-state-inline">No hay historico de cursos para este alumno.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

    @else
        <div class="empty-state">No hay historico de cursos para este alumno.</div>
    @endif
</div>

@if($student->isExtracurricular() || $student->school_grade || $student->extracurricular_level || $student->teacher_observations)
<div class="card" id="student-extracurricular">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Actividad extracurricular</h2>
        <div class="entity-sub">Datos de la planilla de inscripción</div>
    </div>
    <div class="detail-list detail-list-soft">
        <div><strong>Grado / Año:</strong> {{ $student->school_grade ?: 'N/D' }} · <strong>Sección:</strong> {{ $student->school_section ?: 'N/D' }}</div>
        <div><strong>Edad:</strong> {{ $student->age ? $student->age.' años' : 'N/D' }}</div>
        <div><strong>Teléfono de emergencia:</strong> {{ $student->emergency_phone ?: 'N/D' }}</div>
        <div><strong>Nivel / Dominio actual:</strong> {{ $student->extracurricular_level ?: 'N/D' }}</div>
        <div><strong>Objetivos / Competencias:</strong> {{ $student->extracurricular_objectives ?: 'N/D' }}</div>
        <div><strong>Observaciones del docente:</strong> {{ $student->teacher_observations ?: 'Sin observaciones' }}</div>
        <div><strong>Condición de pago / Matrícula:</strong> {{ $student->payment_condition ?: 'N/D' }}</div>
    </div>
</div>
@endif
</section>
