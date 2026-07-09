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
        <label>Status</label>
        <select name="status" required>
            @foreach(['active', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status', $program->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div style="grid-column:1/-1;">
        <label>Descripción</label>
        <textarea name="description">{{ old('description', $program->description ?? '') }}</textarea>
    </div>
    <div>
        <label>Precio base por defecto (EUR)</label>
        <input type="number" name="base_price_eur" min="0" step="0.01" value="{{ old('base_price_eur', $program->base_price_eur ?? '') }}" placeholder="Ej. 240.00">
        <p class="entity-sub">Se usa cuando un nivel no tiene precio propio definido.</p>
    </div>
</div>
