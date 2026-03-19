<div class="grid-2">
    <div>
        <label>Campus</label>
        <select name="campus_id" required>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected((string) old('campus_id', $level->campus_id ?? '') === (string) $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Nombre</label>
        <input name="name" value="{{ old('name', $level->name ?? '') }}" required>
    </div>
    <div>
        <label>Código</label>
        <input name="code" value="{{ old('code', $level->code ?? '') }}">
    </div>
    <div>
        <label>Orden</label>
        <input type="number" min="0" max="999" name="sort_order" value="{{ old('sort_order', $level->sort_order ?? 0) }}">
    </div>
    <div style="grid-column:1/-1;">
        <label>Descripción</label>
        <textarea name="description">{{ old('description', $level->description ?? '') }}</textarea>
    </div>
</div>
