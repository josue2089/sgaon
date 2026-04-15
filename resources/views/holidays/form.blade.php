<div class="grid-2">
    <div>
        <label>Campus</label>
        <select name="campus_id">
            <option value="">Global</option>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected((string) old('campus_id', $holiday->campus_id ?? '') === (string) $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Nombre</label>
        <input name="name" value="{{ old('name', $holiday->name ?? '') }}" required>
    </div>
    <div>
        <label>Tipo</label>
        <select name="is_recurring" required>
            <option value="0" @selected(!old('is_recurring', $holiday->is_recurring ?? false))>Fecha puntual</option>
            <option value="1" @selected((bool) old('is_recurring', $holiday->is_recurring ?? false))>Recurrente anual</option>
        </select>
    </div>
    <div>
        <label>Fecha puntual</label>
        <input type="date" name="holiday_date" value="{{ old('holiday_date', isset($holiday->holiday_date) ? $holiday->holiday_date?->format('Y-m-d') : '') }}">
    </div>
    <div>
        <label>Mes</label>
        <input type="number" name="month" min="1" max="12" value="{{ old('month', $holiday->month ?? '') }}" placeholder="Ej. 7">
    </div>
    <div>
        <label>Día</label>
        <input type="number" name="day" min="1" max="31" value="{{ old('day', $holiday->day ?? '') }}" placeholder="Ej. 24">
    </div>
    <div style="grid-column:1/-1;">
        <label>Descripción</label>
        <textarea name="description" placeholder="Observación opcional">{{ old('description', $holiday->description ?? '') }}</textarea>
    </div>
    <div>
        <label>Status</label>
        <select name="status" required>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $holiday->status ?? 'active') === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </div>
</div>
