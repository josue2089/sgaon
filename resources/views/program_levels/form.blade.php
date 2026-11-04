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
        <label>Precio base (EUR)</label>
        <input type="number" name="base_price_eur" min="0" step="0.01" value="{{ old('base_price_eur', $level->base_price_eur ?? '') }}" placeholder="Ej. 240.00">
        <p class="entity-sub">Opcional. Si se deja vacío, se usa el precio por defecto del programa.</p>
    </div>
    <div>
        <label>Días antes para recordatorio</label>
        <input type="number" name="reminder_days_before" min="0" max="90" value="{{ old('reminder_days_before', $level->reminder_days_before ?? 5) }}" required>
    </div>
    <div>
        <label>Estado</label>
        <select name="status" required>
            @foreach(['active','inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status', $level->status ?? 'active') === $status)>{{ \App\Support\StatusLabel::label($status) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="level_number">Nivel (agrupa A y B)</label>
        <input id="level_number" type="number" name="level_number" min="1" max="20" value="{{ old('level_number', $level->level_number ?? '') }}" placeholder="Ej. 3">
    </div>
    <div>
        <label for="part">Parte</label>
        <select id="part" name="part">
            <option value="">Sin parte</option>
            @foreach(['A', 'B'] as $part)
                <option value="{{ $part }}" @selected(old('part', $level->part ?? '') === $part)>Part {{ $part }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="cefr_label">Equivalencia CEFR</label>
        <input id="cefr_label" name="cefr_label" maxlength="40" value="{{ old('cefr_label', $level->cefr_label ?? '') }}" placeholder="Ej. B1 Teens, A2 / A2+">
        @error('cefr_label')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="grid-2">
        <div>
            <label for="gse_min">GSE desde</label>
            <input id="gse_min" type="number" name="gse_min" min="10" max="90" value="{{ old('gse_min', $level->gse_min ?? '') }}">
        </div>
        <div>
            <label for="gse_max">GSE hasta</label>
            <input id="gse_max" type="number" name="gse_max" min="10" max="90" value="{{ old('gse_max', $level->gse_max ?? '') }}">
            @error('gse_max')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <p class="entity-sub col-span-full">Si lo dejas vacío, se completa con el rango oficial de Pearson para ese CEFR.</p>
    </div>
    <div>
        <label for="book">Libro</label>
        <input id="book" name="book" maxlength="120" value="{{ old('book', $level->book ?? '') }}" placeholder="Ej. English Aware 3">
    </div>
    <div>
        <label for="next_program_level_id">Siguiente nivel</label>
        <select id="next_program_level_id" name="next_program_level_id">
            <option value="">El siguiente de este programa</option>
            @foreach($nextLevelOptions->groupBy(fn ($option) => $option->program?->name ?? 'Sin programa') as $programName => $options)
                <optgroup label="{{ $programName }}">
                    @foreach($options as $option)
                        <option value="{{ $option->id }}" @selected((int) old('next_program_level_id', $level->next_program_level_id ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <p class="entity-sub">Solo si continúa en otro programa (por ejemplo, Primary 6B → HS1A).</p>
    </div>
    <div class="col-span-full">
        <label>Descripción</label>
        <textarea name="description">{{ old('description', $level->description ?? '') }}</textarea>
    </div>
</div>
