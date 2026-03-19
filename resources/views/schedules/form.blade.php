<div class="grid-2">
    <div>
        <label>Campus</label>
        <select name="campus_id" required>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected(old('campus_id', $schedule->campus_id ?? '') == $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Status</label>
        <select name="status" required>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $schedule->status ?? 'active') === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </div>
    <div style="grid-column:1/-1;">
        <label>Días de la semana</label>
        <div class="day-grid">
            @foreach($dayOptions as $dayCode => $dayLabel)
                <label class="day-pill">
                    <input type="checkbox" name="days[]" value="{{ $dayCode }}" @checked(in_array($dayCode, old('days', $schedule->days ?? []), true))>
                    <span>{{ $dayLabel }}</span>
                </label>
            @endforeach
        </div>
    </div>
    <div>
        <label>Hora de inicio</label>
        <input type="time" name="starts_at" value="{{ old('starts_at', $schedule->starts_at ?? '') }}" required>
    </div>
    <div>
        <label>Hora final</label>
        <input type="time" name="ends_at" value="{{ old('ends_at', $schedule->ends_at ?? '') }}" required>
    </div>
</div>
