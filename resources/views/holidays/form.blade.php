<div class="grid-2">
    <div>
        <label>Campus</label>
        <select name="campus_id" @required(! ($canManageGlobal ?? false))>
            @if($canManageGlobal ?? false)
                <option value="">Global (todas las sedes)</option>
            @endif
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected((string) old('campus_id', $holiday->campus_id ?? '') === (string) $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Nombre</label>
        <input name="name" value="{{ old('name', $holiday->name ?? '') }}" required>
    </div>
    @if($canManageGlobal ?? false)
        <div>
            <label>Clase de día</label>
            <select name="kind">
                <option value="holiday" @selected(old('kind', $holiday->kind ?? 'holiday') === 'holiday')>Feriado</option>
                <option value="school_closure" @selected(old('kind', $holiday->kind ?? 'holiday') === 'school_closure')>Día sin clase del colegio</option>
            </select>
        </div>
    @endif
    <div>
        <label>Tipo</label>
        <select name="is_recurring" required>
            <option value="0" @selected(!old('is_recurring', $holiday->is_recurring ?? false))>Fecha puntual</option>
            <option value="1" @selected((bool) old('is_recurring', $holiday->is_recurring ?? false))>Recurrente anual</option>
        </select>
    </div>
    <div>
        <label>Fecha (desde)</label>
        <input type="date" name="holiday_date" value="{{ old('holiday_date', isset($holiday->holiday_date) ? $holiday->holiday_date?->format('Y-m-d') : '') }}">
    </div>
    <div>
        <label>Hasta (opcional)</label>
        <input type="date" name="end_date" value="{{ old('end_date', isset($holiday->end_date) ? $holiday->end_date?->format('Y-m-d') : '') }}">
        <div class="form-hint">Para vacaciones escolares, puentes o varios días seguidos. Déjalo vacío si es un solo día.</div>
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
