{{-- Selector de grupo/curso con búsqueda: muestra curso, profesor, período y cupos. --}}
@php($fieldId = $id ?? 'group-id')
<div class="searchable-select searchable-select--combo" data-searchable-select data-searchable-combo>
    <label for="{{ $fieldId }}-search">{{ $fieldLabel ?? 'Curso / grupo' }}</label>
    <input type="text" id="{{ $fieldId }}-search" class="searchable-select__search" placeholder="Buscar curso por nombre, nivel, horario o profesor…" autocomplete="off">
    <select name="group_id" id="{{ $fieldId }}" class="searchable-select__list" required>
        <option value="" data-search="">Selecciona un curso</option>
        @foreach($groups as $groupOption)
            @php($seats = $groupOption->capacity ? max(0, (int) $groupOption->capacity - (int) ($groupOption->active_enrollments_count ?? $groupOption->enrollments_count ?? 0)) : null)
            @php($groupLabel = trim(($groupOption->course?->name ?? $groupOption->name).($groupOption->course?->name && $groupOption->course->name !== $groupOption->name ? ' · '.$groupOption->name : '')))
            <option value="{{ $groupOption->id }}"
                data-search="{{ mb_strtolower($groupLabel.' '.($groupOption->course?->teacher?->full_name ?? '').' '.($groupOption->course?->period?->code ?? '')) }}"
                @selected((string) old('group_id', $selected ?? '') === (string) $groupOption->id)
            >{{ $groupLabel }}{{ $groupOption->course?->period?->code ? ' · '.$groupOption->course->period->code : '' }}{{ ! is_null($seats) ? ' · '.$seats.' cupos' : '' }}</option>
        @endforeach
    </select>
</div>
