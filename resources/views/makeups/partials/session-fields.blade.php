{{-- Campos de un bloque recuperativo (crear y editar). #452 --}}
<div>
    <label for="{{ $prefix }}-teacher">Profesor</label>
    <select id="{{ $prefix }}-teacher" name="teacher_id" required>
        <option value="">Selecciona</option>
        @foreach($teachers as $teacher)
            <option value="{{ $teacher->id }}" @selected((string) ($values['teacher_id'] ?? '') === (string) $teacher->id)>{{ $teacher->full_name }}</option>
        @endforeach
    </select>
</div>
<div class="grid-2">
    <div>
        <label for="{{ $prefix }}-program">Programa</label>
        <select id="{{ $prefix }}-program" name="program_id" required>
            <option value="">Selecciona</option>
            @foreach($programs as $program)
                <option value="{{ $program->id }}" @selected((string) ($values['program_id'] ?? '') === (string) $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $prefix }}-level">Nivel</label>
        <select id="{{ $prefix }}-level" name="program_level_id" required data-level-for="{{ $prefix }}-program">
            <option value="">Selecciona el programa primero</option>
            @foreach($programLevels as $level)
                <option value="{{ $level->id }}" data-program-id="{{ $level->program_id }}" @selected((string) ($values['program_level_id'] ?? '') === (string) $level->id)>{{ $level->name }}</option>
            @endforeach
        </select>
        @error('program_level_id')<div class="form-error">{{ $message }}</div>@enderror
    </div>
</div>
<div class="grid-3">
    <div><label for="{{ $prefix }}-date">Fecha</label><input id="{{ $prefix }}-date" type="date" name="session_date" value="{{ $values['session_date'] ?? '' }}" required></div>
    <div><label for="{{ $prefix }}-start">Inicio</label><input id="{{ $prefix }}-start" type="time" name="starts_at" value="{{ $values['starts_at'] ?? '' }}" required></div>
    <div><label for="{{ $prefix }}-end">Fin</label><input id="{{ $prefix }}-end" type="time" name="ends_at" value="{{ $values['ends_at'] ?? '' }}" required></div>
</div>
<div>
    <label for="{{ $prefix }}-capacity">Cupo</label>
    <input id="{{ $prefix }}-capacity" type="number" min="1" max="50" name="capacity" value="{{ $values['capacity'] ?? 8 }}" required>
    @error('capacity')<div class="form-error">{{ $message }}</div>@enderror
</div>
<div><label for="{{ $prefix }}-notes">Notas</label><textarea id="{{ $prefix }}-notes" name="notes">{{ $values['notes'] ?? '' }}</textarea></div>
