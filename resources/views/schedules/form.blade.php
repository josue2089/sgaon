<div class="grid-2">
    <div>
        <label for="schedule-campus-id">Campus</label>
        <select id="schedule-campus-id" name="campus_id" required>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected(old('campus_id', $schedule->campus_id ?? '') == $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="schedule-status">Estado</label>
        <select id="schedule-status" name="status" required>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $schedule->status ?? 'active') === $status)>{{ \App\Support\StatusLabel::label($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-full">
        <label id="schedule-days-label">Días de la semana</label>
        <div class="day-grid" role="group" aria-labelledby="schedule-days-label">
            @foreach($dayOptions as $dayCode => $dayLabel)
                <label class="day-pill">
                    <input type="checkbox" name="days[]" value="{{ $dayCode }}" @checked(in_array($dayCode, old('days', $schedule->days ?? []), true))>
                    <span>{{ $dayLabel }}</span>
                </label>
            @endforeach
        </div>
    </div>
    <div>
        <label for="schedule-starts-at">Hora de inicio</label>
        <input id="schedule-starts-at" type="time" name="starts_at" value="{{ old('starts_at', isset($schedule->starts_at) ? substr((string) $schedule->starts_at, 0, 5) : '') }}" required>
    </div>
    <div>
        <label for="schedule-ends-at">Hora final</label>
        <input id="schedule-ends-at" type="time" name="ends_at" value="{{ old('ends_at', isset($schedule->ends_at) ? substr((string) $schedule->ends_at, 0, 5) : '') }}" required>
    </div>
</div>
