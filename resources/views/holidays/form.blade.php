{{-- #453: cada campo con x-form.field (etiqueta ligada al control y error junto al campo). --}}
<div class="grid-2">
    <x-form.field label="Sede" name="campus_id" for="holiday-campus" :required="! ($canManageGlobal ?? false)">
        <select id="holiday-campus" name="campus_id" @required(! ($canManageGlobal ?? false))>
            @if($canManageGlobal ?? false)
                <option value="">Global (todas las sedes)</option>
            @endif
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected((string) old('campus_id', $holiday->campus_id ?? '') === (string) $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </x-form.field>
    <x-form.field label="Nombre" name="name" for="holiday-name" required>
        <input id="holiday-name" name="name" value="{{ old('name', $holiday->name ?? '') }}" required>
    </x-form.field>
    @if($canManageGlobal ?? false)
        <x-form.field label="Clase de día" name="kind" for="holiday-kind">
            <select id="holiday-kind" name="kind">
                <option value="holiday" @selected(old('kind', $holiday->kind ?? 'holiday') === 'holiday')>Feriado</option>
                <option value="school_closure" @selected(old('kind', $holiday->kind ?? 'holiday') === 'school_closure')>Día sin clase del colegio</option>
            </select>
        </x-form.field>
    @endif
    <x-form.field label="Tipo" name="is_recurring" for="holiday-recurring" required>
        <select id="holiday-recurring" name="is_recurring" required>
            <option value="0" @selected(!old('is_recurring', $holiday->is_recurring ?? false))>Fecha puntual</option>
            <option value="1" @selected((bool) old('is_recurring', $holiday->is_recurring ?? false))>Recurrente anual</option>
        </select>
    </x-form.field>
    <x-form.field label="Fecha (desde)" name="holiday_date" for="holiday-date">
        <input id="holiday-date" type="date" name="holiday_date" value="{{ old('holiday_date', isset($holiday->holiday_date) ? $holiday->holiday_date?->format('Y-m-d') : '') }}">
    </x-form.field>
    <x-form.field label="Hasta (opcional)" name="end_date" for="holiday-end" hint="Para vacaciones escolares, puentes o varios días seguidos. Déjalo vacío si es un solo día.">
        <input id="holiday-end" type="date" name="end_date" value="{{ old('end_date', isset($holiday->end_date) ? $holiday->end_date?->format('Y-m-d') : '') }}">
    </x-form.field>
    <x-form.field label="Mes" name="month" for="holiday-month">
        <input id="holiday-month" type="number" name="month" min="1" max="12" value="{{ old('month', $holiday->month ?? '') }}" placeholder="Ej. 7">
    </x-form.field>
    <x-form.field label="Día" name="day" for="holiday-day">
        <input id="holiday-day" type="number" name="day" min="1" max="31" value="{{ old('day', $holiday->day ?? '') }}" placeholder="Ej. 24">
    </x-form.field>
    <x-form.field label="Descripción" name="description" for="holiday-description" class="col-span-full">
        <textarea id="holiday-description" name="description" placeholder="Observación opcional">{{ old('description', $holiday->description ?? '') }}</textarea>
    </x-form.field>
    <x-form.field label="Estado" name="status" for="holiday-status" required>
        <select id="holiday-status" name="status" required>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $holiday->status ?? 'active') === $status)>{{ \App\Support\StatusLabel::label($status) }}</option>
            @endforeach
        </select>
    </x-form.field>
</div>
