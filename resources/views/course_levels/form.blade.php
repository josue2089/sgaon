<div class="grid-2">
    <div>
        <label>Etapa</label>
        <select name="stage" required>
            @foreach($stageOptions as $stage)
                <option value="{{ $stage }}" @selected(old('stage', $level->stage ?? '') === $stage)>{{ $stage }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Nombre</label>
        <input name="name" value="{{ old('name', $level->name ?? '') }}" required>
    </div>
    <div>
        <label>Código</label>
        <input name="code" value="{{ old('code', $level->code ?? '') }}" required>
    </div>
    <div>
        <label>Referencia CEFR</label>
        <input name="cefr_reference" value="{{ old('cefr_reference', $level->cefr_reference ?? '') }}">
    </div>
    <div>
        <label>Posición en escala</label>
        <input type="number" min="1" max="99" name="scale_position" value="{{ old('scale_position', $level->scale_position ?? 1) }}" required>
    </div>
    <div>
        <label>Total de la escala</label>
        <input type="number" min="1" max="99" name="scale_total" value="{{ old('scale_total', $level->scale_total ?? 12) }}" required>
    </div>
    <div>
        <label>Días antes para recordatorio</label>
        <input type="number" min="0" max="90" name="reminder_days_before" value="{{ old('reminder_days_before', $level->reminder_days_before ?? 5) }}" required>
    </div>
    <div>
        <label>Status</label>
        <select name="status" required>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $level->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div style="grid-column:1/-1;">
        <label>Descripción</label>
        <textarea name="description">{{ old('description', $level->description ?? '') }}</textarea>
    </div>
</div>
