<div class="grid-2">
    <div>
        <label>Nombre</label>
        <input name="name" value="{{ old('name', $program->name ?? '') }}" required>
    </div>
    <div>
        <label>Código</label>
        <input name="code" value="{{ old('code', $program->code ?? '') }}" required>
    </div>
    <div>
        <label>Estado</label>
        <select name="status" required>
            @foreach(['active', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status', $program->status ?? 'active') === $status)>{{ \App\Support\StatusLabel::label($status) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="checkbox-inline">
            <input type="checkbox" name="is_extracurricular" value="1" @checked(old('is_extracurricular', $program->is_extracurricular ?? false))>
            Extracurricular
        </label>
        <p class="entity-sub">Actividades en colegios (inglés, robótica, danza, fútbol…) con cursos de todo el año escolar.</p>
    </div>
    <div>
        <label for="audience">Público</label>
        <select id="audience" name="audience">
            <option value="">—</option>
            @foreach(\App\Support\CefrScale::AUDIENCES as $value => $label)
                <option value="{{ $value }}" @selected(old('audience', $program->audience ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="grid-2">
        <div>
            <label for="age_min">Edad desde</label>
            <input id="age_min" type="number" name="age_min" min="1" max="99" value="{{ old('age_min', $program->age_min ?? '') }}">
        </div>
        <div>
            <label for="age_max">Edad hasta</label>
            <input id="age_max" type="number" name="age_max" min="1" max="99" value="{{ old('age_max', $program->age_max ?? '') }}" placeholder="Sin límite">
        </div>
    </div>
    <div>
        <label class="checkbox-inline">
            <input type="checkbox" name="uses_cefr" value="1" @checked(old('uses_cefr', $program->uses_cefr ?? false))>
            Usa equivalencias CEFR / GSE
        </label>
        <p class="entity-sub">Desmárcalo para programas que no son de inglés (robótica, deportes…).</p>
    </div>
    <div class="col-span-full">
        <label>Descripción</label>
        <textarea name="description">{{ old('description', $program->description ?? '') }}</textarea>
    </div>
    <div>
        <label>Precio base por defecto (EUR)</label>
        <input type="number" name="base_price_eur" min="0" step="0.01" value="{{ old('base_price_eur', $program->base_price_eur ?? '') }}" placeholder="Ej. 240.00">
        <p class="entity-sub">Se usa cuando un nivel no tiene precio propio definido.</p>
    </div>
</div>
