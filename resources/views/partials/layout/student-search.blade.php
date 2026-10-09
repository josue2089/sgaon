<div class="fi-search fi-search--{{ $searchId }}" data-student-search data-url="{{ route('students.search') }}">
    <label class="sr-only" for="student-search-{{ $searchId }}">Buscar alumno</label>
    <svg class="fi-search-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m20 20-4.2-4.2M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    <input id="student-search-{{ $searchId }}" type="search" autocomplete="off" placeholder="Buscar alumno…"
           role="combobox" aria-expanded="false" aria-controls="student-search-{{ $searchId }}-results" aria-autocomplete="list"
           data-student-search-input>
    <div class="fi-search-results" id="student-search-{{ $searchId }}-results" role="listbox" hidden data-student-search-results></div>
</div>
