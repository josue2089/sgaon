<div class="grid-2">
    <div>
        <label>Campus</label>
        <select name="campus_id" required>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected(old('campus_id', $period->campus_id ?? '') == $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Código</label>
        <input name="code" value="{{ old('code', $period->code ?? '') }}" placeholder="Ej. 2026-Q1" required>
    </div>
    <div class="col-span-full">
        <label>Descripción</label>
        <input name="description" value="{{ old('description', $period->description ?? '') }}" placeholder="Ej. Primer trimestre académico 2026">
    </div>
    <div>
        <label>Estado</label>
        <select name="status" required>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $period->status ?? 'active') === $status)>{{ \App\Support\StatusLabel::label($status) }}</option>
            @endforeach
        </select>
    </div>
</div>
