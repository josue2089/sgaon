@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Abrir siguiente nivel</h1>
        <p class="page-subtitle">Desde {{ $course->name }}{{ $course->end_date ? ' · termina el '.$course->end_date->format('d/m/Y') : '' }}</p>
    </div>
    <a class="btn secondary" href="{{ route('courses.show', $course) }}">Volver al curso</a>
</div>

<form method="POST" action="{{ route('courses.next-level.store', $course) }}" class="card stack-sm" data-guard-submit data-confirm="Se creará el curso y se inscribirá a los alumnos seleccionados, con su mensualidad. ¿Continuar?">
    @csrf
    <div class="grid-2">
        <div>
            <label for="program_level_id">Nivel</label>
            <select id="program_level_id" name="program_level_id" required>
                @foreach($levels->groupBy(fn ($level) => $level->program?->name) as $programName => $options)
                    <optgroup label="{{ $programName }}">
                        @foreach($options as $level)
                            <option value="{{ $level->id }}" @selected((int) old('program_level_id', $nextLevel->id) === (int) $level->id)>{{ $level->name }}{{ $level->cefr_label ? ' · '.$level->cefr_label : '' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div>
            <label for="teacher_id">Profesor</label>
            <select id="teacher_id" name="teacher_id" required>
                <option value="">Selecciona un profesor</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected((int) old('teacher_id', $course->teacher_id) === (int) $teacher->id)>{{ $teacher->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="schedule_template_id">Horario</label>
            <select id="schedule_template_id" name="schedule_template_id" required>
                @foreach($schedules as $schedule)
                    <option value="{{ $schedule->id }}" @selected((int) old('schedule_template_id', $course->schedule_template_id) === (int) $schedule->id)>{{ $schedule->display_label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="period_id">Período</label>
            <select id="period_id" name="period_id" required>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}" @selected((int) old('period_id', $course->period_id) === (int) $period->id)>{{ $period->code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="start_date">Fecha de inicio</label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date', $startDate) }}" required>
        </div>
        <div>
            <label for="academic_hours">Horas académicas</label>
            <input id="academic_hours" type="number" name="academic_hours" min="1" max="500" value="{{ old('academic_hours', $nextLevel->academic_hours ?: 40) }}" required>
        </div>
    </div>

    <div>
        <h2 class="section-title section-title-md">Alumnos ({{ $students->count() }})</h2>
        <p class="entity-sub">Quedan inscritos con su mensualidad, como en una inscripción normal.</p>
        <div class="stack-xs">
            @forelse($students as $row)
                <div>
                <label class="checkbox-inline">
                    <input type="checkbox" name="student_ids[]" value="{{ $row['student']->id }}" @checked(! $row['blocked']) @disabled($row['blocked'])>
                    {{ $row['student']->full_name }}
                    @if($row['blocked'])
                        <span class="entity-sub">· Evaluación final en Need Support: no puede pasar de nivel.</span>
                    @elseif($row['pending_grade'])
                        <span class="entity-sub">· Aún sin evaluación final.</span>
                    @endif
                </label>
                </div>
            @empty
                <div class="empty-state-inline">Este curso no tiene alumnos activos.</div>
            @endforelse
        </div>
    </div>

    @include('partials.finance.skip-tuition')

    <div class="form-actions">
        <button class="btn" type="submit" data-submit-busy-label="Abriendo…">Abrir siguiente nivel</button>
    </div>
</form>
@endsection
