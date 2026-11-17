<div class="grid-2">
    <div>
        <label for="course-level-stage">Etapa</label>
        <select id="course-level-stage" name="stage" required>
            @foreach($stageOptions as $stage)
                <option value="{{ $stage }}" @selected(old('stage', $level->stage ?? '') === $stage)>{{ $stage }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="course-level-name">Nombre</label>
        <input id="course-level-name" name="name" value="{{ old('name', $level->name ?? '') }}" required>
    </div>
    <div>
        <label for="course-level-code">Código</label>
        <input id="course-level-code" name="code" value="{{ old('code', $level->code ?? '') }}" required>
    </div>
    <div>
        <label for="course-level-cefr-reference">Referencia CEFR</label>
        <input id="course-level-cefr-reference" name="cefr_reference" value="{{ old('cefr_reference', $level->cefr_reference ?? '') }}">
    </div>
    <div>
        <label for="course-level-scale-position">Posición en escala</label>
        <input id="course-level-scale-position" type="number" min="1" max="99" name="scale_position" value="{{ old('scale_position', $level->scale_position ?? 1) }}" required>
    </div>
    <div>
        <label for="course-level-scale-total">Total de la escala</label>
        <input id="course-level-scale-total" type="number" min="1" max="99" name="scale_total" value="{{ old('scale_total', $level->scale_total ?? 12) }}" required>
    </div>
    <div>
        <label for="course-level-reminder-days-before">Días antes para recordatorio</label>
        <input id="course-level-reminder-days-before" type="number" min="0" max="90" name="reminder_days_before" value="{{ old('reminder_days_before', $level->reminder_days_before ?? 5) }}" required>
    </div>
    <div>
        <label for="course-level-status">Estado</label>
        <select id="course-level-status" name="status" required>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $level->status ?? 'active') === $status)>{{ \App\Support\StatusLabel::label($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-full">
        <label for="course-level-description">Descripción</label>
        <textarea id="course-level-description" name="description">{{ old('description', $level->description ?? '') }}</textarea>
    </div>
</div>
