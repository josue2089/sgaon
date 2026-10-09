@php($current = request()->route()?->getName())
<nav class="section-tabs" aria-label="Secciones de alumnos">
    <a href="{{ route('students.index') }}" @class(['section-tab', 'is-active' => $current === 'students.index']) aria-current="{{ $current === 'students.index' ? 'page' : 'false' }}">Activos</a>
    <a href="{{ route('students.historical.index') }}" @class(['section-tab', 'is-active' => str_starts_with((string) $current, 'students.historical.')]) aria-current="{{ str_starts_with((string) $current, 'students.historical.') ? 'page' : 'false' }}">Históricos</a>
    @if(\Illuminate\Support\Facades\Route::has('enrollments.index'))
        <a href="{{ route('enrollments.index') }}" @class(['section-tab', 'is-active' => str_starts_with((string) $current, 'enrollments.')]) aria-current="{{ str_starts_with((string) $current, 'enrollments.') ? 'page' : 'false' }}">Inscripciones</a>
    @endif
</nav>
