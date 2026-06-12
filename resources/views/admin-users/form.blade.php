@php
    $isEdit = isset($user) && $user->exists;
@endphp
<div>
    <label>Nombre</label>
    <input name="name" value="{{ old('name', $user->name ?? '') }}" required>
</div>
<div>
    <label>Email</label>
    <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required>
</div>
<div>
    <label>Teléfono</label>
    <input name="phone" value="{{ old('phone', $user->phone ?? '') }}">
</div>
<div>
    <label>Estado</label>
    <select name="status">
        @foreach(['active' => 'Activo', 'inactive' => 'Inactivo'] as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $user->status ?? 'active') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div>
    <label>Alcance de sedes</label>
    <div class="stack-sm">
        <label class="student-picker-toggle">
            <input type="radio" name="access_mode" value="all" @checked(old('access_mode', $accessMode ?? 'selected') === 'all')>
            <span>Todas las sedes</span>
        </label>
        <label class="student-picker-toggle">
            <input type="radio" name="access_mode" value="selected" @checked(old('access_mode', $accessMode ?? 'selected') === 'selected')>
            <span>Sedes seleccionadas</span>
        </label>
    </div>
</div>
<div>
    <label>Sedes asignadas</label>
    <div class="stack-sm">
        @foreach($campuses as $campus)
            <label class="student-picker-toggle">
                <input
                    type="checkbox"
                    name="campus_ids[]"
                    value="{{ $campus->id }}"
                    @checked(in_array($campus->id, old('campus_ids', $selectedCampusIds ?? [])))
                >
                <span>{{ $campus->name }} ({{ $campus->code }})</span>
            </label>
        @endforeach
    </div>
    @error('campus_ids')
        <div class="flash err">{{ $message }}</div>
    @enderror
</div>
@if($isEdit)
    <p class="table-sub">Para reenviar credenciales usa el botón en la lista de usuarios.</p>
@else
    <p class="table-sub">Al guardar se generará una contraseña temporal y se enviará por email.</p>
@endif
