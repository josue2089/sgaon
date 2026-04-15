<div class="grid-2">
    <div>
        <label>Nombre</label>
        <input name="name" value="{{ old('name', $level->name ?? '') }}" required>
    </div>
    <div>
        <label>Código</label>
        <input name="code" value="{{ old('code', $level->code ?? '') }}" required>
    </div>
    <div>
        <label>Orden dentro del programa</label>
        <input type="number" name="sort_order" min="1" max="99" value="{{ old('sort_order', $level->sort_order ?? 1) }}" required>
    </div>
    <div>
        <label>Total del programa</label>
        <input type="number" name="program_total" min="1" max="99" value="{{ old('program_total', $level->program_total ?? 1) }}" required>
    </div>
    <div>
        <label>Horas académicas</label>
        <input type="number" name="academic_hours" min="1" max="500" value="{{ old('academic_hours', $level->academic_hours ?? 40) }}" required>
    </div>
    <div>
        <label>Días antes para recordatorio</label>
        <input type="number" name="reminder_days_before" min="0" max="90" value="{{ old('reminder_days_before', $level->reminder_days_before ?? 5) }}" required>
    </div>
    <div>
        <label>Status</label>
        <select name="status" required>
            @foreach(['active','inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status', $level->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div style="grid-column:1/-1;">
        <label>Descripción</label>
        <textarea name="description">{{ old('description', $level->description ?? '') }}</textarea>
    </div>
</div>
