<div class="grid-2">
    <div>
        <label>Nombre</label>
        <input name="name" value="{{ old('name', $campus->name ?? '') }}" required>
    </div>
    <div>
        <label>Código</label>
        <input name="code" value="{{ old('code', $campus->code ?? '') }}" required>
    </div>
    <div>
        <label>Ciudad</label>
        <input name="city" value="{{ old('city', $campus->city ?? '') }}">
    </div>
    <div>
        <label>Estado / Provincia</label>
        <input name="state" value="{{ old('state', $campus->state ?? '') }}">
    </div>
    <div>
        <label>País</label>
        <input name="country" value="{{ old('country', $campus->country ?? '') }}">
    </div>
    <div>
        <label>Status</label>
        <select name="status" required>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $campus->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
</div>
