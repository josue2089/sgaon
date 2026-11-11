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
    <div>
        <label for="lesson-type">Tipo de clase</label>
        <select id="lesson-type" name="lesson_type">
            <option value="">Sin tipo</option>
            @foreach(\App\Support\LessonTemplates::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('lesson_type', $lesson->lesson_type ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-full">
        <label>Actividades de la clase</label>
        @php($selectedTags = old('activity_tags', $lesson->activity_tags ?? []))
        <div class="checkbox-grid">
            @foreach(\App\Support\LessonTemplates::TAGS as $value => $label)
                <label class="checkbox-inline"><input type="checkbox" name="activity_tags[]" value="{{ $value }}" @checked(in_array($value, $selectedTags, true))> {{ $label }}</label>
            @endforeach
        </div>
    </div>
    <div class="col-span-full">
        <label>Contenido</label>
        <textarea name="content" required>{{ old('content', $lesson->content ?? '') }}</textarea>
    </div>
    <div class="col-span-full">
        <label>Nota</label>
        <textarea name="notes">{{ old('notes', $lesson->notes ?? '') }}</textarea>
    </div>
</div>
