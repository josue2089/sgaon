{{-- Selector de alumnos con búsqueda (nombre, email, cédula, representante). Reemplaza los <select> con todos los alumnos.
     Con 'remote' => true (#452) solo se pasan los alumnos ya elegidos y el resto se busca en students.search al escribir. --}}
@php($fieldId = $id ?? \Illuminate\Support\Str::slug($name, '-'))
@php($selectedIds = collect((array) ($selected ?? []))->map(fn ($value) => (string) $value)->all())
<div class="searchable-select {{ empty($multiple) ? 'searchable-select--combo' : '' }}" data-searchable-select @if(empty($multiple)) data-searchable-combo @endif @if(!empty($remote)) data-remote-url="{{ route('students.search') }}" @endif>
    <label for="{{ $fieldId }}-search">{{ $fieldLabel ?? 'Alumno' }}</label>
    <input type="text" id="{{ $fieldId }}-search" class="searchable-select__search" placeholder="{{ !empty($remote) ? 'Escribe al menos 2 letras del nombre, cédula o representante…' : 'Buscar alumno por nombre, cédula o representante…' }}" autocomplete="off">
    <select name="{{ $name }}" id="{{ $fieldId }}" class="searchable-select__list" @if(!empty($multiple)) multiple @endif @if(!empty($required)) required @endif>
        @isset($placeholder)
            <option value="" data-search="">{{ $placeholder }}</option>
        @endisset
        @foreach($students as $studentOption)
            <option value="{{ $studentOption->id }}" data-search="{{ \App\Support\StudentSearch::haystack($studentOption) }}" @selected(in_array((string) $studentOption->id, $selectedIds, true))>{{ $studentOption->full_name }}{{ $studentOption->document_id ? ' · '.$studentOption->document_id : '' }}{{ $studentOption->email ? ' · '.$studentOption->email : '' }}</option>
        @endforeach
    </select>
</div>
