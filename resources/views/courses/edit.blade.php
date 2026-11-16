@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Editar curso ✏️</h1><p class="page-subtitle">Actualiza la planificación académica y la asignación del curso</p></div></div>
<div class="card"><form method="POST" action="{{ route('courses.update',$course) }}" id="course-edit-form">@csrf @method('PUT') @include('courses.form')<div class="form-actions"><button class="btn">Actualizar curso</button><a class="btn secondary" href="{{ route('courses.show', $course) }}">Volver al detalle</a></div></form></div>

{{-- #444: vista previa del calendario antes de mover las clases del curso. --}}
@if($preview = session('calendarPreview'))
    @php($fmt = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->locale('es')->isoFormat('ddd D/MM/YYYY') : '—')
    <x-ui.modal id="calendar-preview-modal" title="Vista previa del calendario" size="md" data-open-on-load="1"
                subtitle="Así quedan las clases del curso con el cambio. Todavía no se guardó nada.">
        <p class="entity-sub">
            Nuevo inicio: <strong>{{ $fmt($preview['start_after']) }}</strong>
            @if($preview['end_after']) · fin: <strong>{{ $fmt($preview['end_after']) }}</strong>@endif
            · {{ $preview['before_count'] }} clase(s) antes, {{ $preview['after_count'] }} después.
            Las clases con asistencia se mantienen.
        </p>
        <div class="table-wrap calendar-preview-table">
            <table class="data-table">
                <thead><tr><th>#</th><th>Antes</th><th>Después</th></tr></thead>
                <tbody>
                @foreach($preview['rows'] as $i => $row)
                    <tr @class(['calendar-preview-same' => $row['before'] === $row['after']])>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $fmt($row['before']) }}@if($row['before_attendance'])<div class="table-sub">Con asistencia</div>@endif</td>
                        <td>{{ $fmt($row['after']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <x-slot:footer>
            <button class="btn secondary" type="button" data-modal-close>Seguir editando</button>
            <button class="btn" type="submit" form="course-edit-form" name="calendar_confirmed" value="1">Aplicar cambio</button>
        </x-slot:footer>
    </x-ui.modal>
@endif
@endsection
