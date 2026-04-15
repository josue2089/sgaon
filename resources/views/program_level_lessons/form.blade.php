<div class="grid-2">
    <div>
        <label>Número de clase</label>
        <input type="number" name="class_number" min="1" max="999" value="{{ old('class_number', $lesson->class_number ?? 1) }}" required>
    </div>
    <div>
        <label>Orden</label>
        <input type="number" name="sort_order" min="1" max="999" value="{{ old('sort_order', $lesson->sort_order ?? 1) }}" required>
    </div>
    <div>
        <label>Unidad / módulo</label>
        <input name="unit" value="{{ old('unit', $lesson->unit ?? '') }}">
    </div>
    <div style="grid-column:1/-1;">
        <label>Contenido</label>
        <textarea name="content" required>{{ old('content', $lesson->content ?? '') }}</textarea>
    </div>
    <div style="grid-column:1/-1;">
        <label>Nota</label>
        <textarea name="notes">{{ old('notes', $lesson->notes ?? '') }}</textarea>
    </div>
</div>
