<div class="grid-2">
    <div>
        <label>Campus</label>
        <select name="campus_id" required>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected(old('campus_id',$course->campus_id ?? '') == $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Programa</label>
        <select name="program_id" data-program-select required>
            <option value="">Seleccione</option>
            @foreach($programs as $program)
                <option value="{{ $program->id }}" data-extracurricular="{{ $program->is_extracurricular ? 1 : 0 }}" @selected(old('program_id',$course->program_id ?? '') == $program->id)>{{ $program->name }}{{ $program->is_extracurricular ? ' (extracurricular)' : '' }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Nivel del programa</label>
        <select name="program_level_id" data-program-level-select required disabled>
            <option value="">Seleccione</option>
            @foreach($programLevels as $programLevel)
                <option
                    value="{{ $programLevel->id }}"
                    data-program-id="{{ $programLevel->program_id }}"
                    data-academic-hours="{{ $programLevel->academic_hours }}" data-level-name="{{ $programLevel->name }}"
                    @selected(old('program_level_id',$course->program_level_id ?? '') == $programLevel->id)
                >
                    {{ $programLevel->sort_order }}/{{ $programLevel->program_total }} · {{ $programLevel->name }}
                </option>
            @endforeach
        </select>
        <div class="form-hint">Primero selecciona el programa. Solo verás los niveles que pertenecen a ese programa.</div>
    </div>
    <div>
        <label>Nombre estructurado</label>
        <input name="name" data-course-name-input value="{{ old('name',$course->display_name ?? $course->name ?? '') }}" readonly>
        <div class="form-hint">Se genera automáticamente con nivel, horario y profesor.</div>
    </div>
    <div>
        <label>Código</label>
        <input name="code" value="{{ old('code',$course->code ?? '') }}" placeholder="Ej. B2-MAR-2026">
    </div>
    <div>
        <label>Profesor asignado</label>
        <select name="teacher_id" data-course-teacher-select required>
            <option value="">Seleccione</option>
            @foreach($teachers as $teacher)
                <option value="{{ $teacher->id }}" data-teacher-last-name="{{ $teacher->last_name ?: $teacher->full_name }}" @selected((string) old('teacher_id',$course->teacher_id ?? '') === (string) $teacher->id)>{{ $teacher->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Período</label>
        <select name="period_id" required>
            <option value="">Seleccione</option>
            @foreach($periods as $period)
                <option value="{{ $period->id }}" @selected((string) old('period_id',$course->period_id ?? '') === (string) $period->id)>{{ $period->code }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Horario asignado</label>
        <select name="schedule_template_id" data-course-schedule-select required>
            <option value="">Seleccione</option>
            @foreach($schedules as $schedule)
                <option value="{{ $schedule->id }}" data-schedule-compact-label="{{ $schedule->compact_label }}" @selected((string) old('schedule_template_id',$course->schedule_template_id ?? '') === (string) $schedule->id)>{{ $schedule->display_label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Fecha de inicio</label>
        <input type="date" name="start_date" value="{{ old('start_date',isset($course->start_date) ? $course->start_date?->format('Y-m-d') : '') }}" required>
    </div>
    @php($courseIsExtracurricular = (bool) optional($programs->firstWhere('id', (int) old('program_id', $course->program_id ?? 0)))->is_extracurricular)
    <div data-course-hours-field @if($courseIsExtracurricular) hidden @endif>
        <label>Duración del curso</label>
        <input type="number" name="academic_hours" data-academic-hours-input min="1" max="500" value="{{ old('academic_hours',$course->academic_hours ?? '') }}" placeholder="Ej. 40" @disabled($courseIsExtracurricular) @required(! $courseIsExtracurricular)>
        <div class="form-hint">Se calcula con hora académica de 45 minutos.</div>
    </div>
    <div data-course-end-date-field @if(! $courseIsExtracurricular) hidden @endif>
        <label>Fecha de fin (fin del año escolar)</label>
        <input type="date" name="end_date" value="{{ old('end_date', isset($course->end_date) && $courseIsExtracurricular ? $course->end_date?->format('Y-m-d') : '') }}" @disabled(! $courseIsExtracurricular) @required($courseIsExtracurricular)>
        <div class="form-hint">Curso extracurricular: se generan todas las clases del horario hasta esta fecha, sin límite.</div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const programSelect = document.querySelector('select[name="program_id"][data-program-select]');
            const hoursField = document.querySelector('[data-course-hours-field]');
            const endDateField = document.querySelector('[data-course-end-date-field]');
            if (!programSelect || !hoursField || !endDateField) {
                return;
            }
            const toggle = function () {
                const option = programSelect.selectedOptions[0];
                const extracurricular = option && option.dataset.extracurricular === '1';
                hoursField.hidden = extracurricular;
                endDateField.hidden = !extracurricular;
                hoursField.querySelector('input').disabled = extracurricular;
                hoursField.querySelector('input').required = !extracurricular;
                endDateField.querySelector('input').disabled = !extracurricular;
                endDateField.querySelector('input').required = extracurricular;
            };
            programSelect.addEventListener('change', toggle);
        });
    </script>
    <div>
        <label>Status</label>
        <select name="status" required>
            @foreach(['active','inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status',$course->status ?? 'active') === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </div>
    <div style="grid-column:1/-1;">
        <label>Descripción</label>
        <textarea name="description" placeholder="Descripción breve del curso">{{ old('description',$course->description ?? '') }}</textarea>
    </div>
    <div style="grid-column:1/-1;">
        <label>Estudiantes iniciales</label>
        <select name="student_ids[]" multiple size="8">
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected(in_array((string) $student->id, array_map('strval', old('student_ids', $selectedStudentIds ?? [])), true))>{{ $student->full_name }}{{ $student->email ? ' · '.$student->email : '' }}</option>
            @endforeach
        </select>
        <div class="form-hint">Al guardar, el sistema generará el calendario y agregará estos estudiantes al curso.</div>
    </div>
</div>
