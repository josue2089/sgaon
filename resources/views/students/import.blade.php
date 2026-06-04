@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Importar alumnos</h1>
        <p class="page-subtitle">Carga masiva desde Excel CCL (ficha y programa de inscripción)</p>
    </div>
    <a class="btn secondary" href="{{ route('students.index') }}">Volver a alumnos</a>
</div>

@if(session('error'))
    <div class="card" style="margin-bottom:1rem;color:#b91c1c;">{{ session('error') }}</div>
@endif

@if(!$preview)
    <div class="card">
        <form method="POST" action="{{ route('students.import.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                @if($isMaster)
                    <label>
                        Sede
                        <select name="campus_id" required>
                            @foreach($campuses as $campus)
                                <option value="{{ $campus->id }}" @selected((string) old('campus_id', $defaultCampusId) === (string) $campus->id)>{{ $campus->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <input type="hidden" name="campus_id" value="{{ $defaultCampusId }}">
                    <p class="page-subtitle">Sede: {{ $campuses->first()?->name }}</p>
                @endif
                <label>
                    Archivo Excel (.xlsx)
                    <input type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                </label>
            </div>
            <p class="page-subtitle" style="margin-top:1rem;">
                Formato esperado: columnas Nombre, 2do Nombre, Apellido, Edad, Nivel y Status (encabezado en fila 4). La columna ID del Excel es solo un contador y se ignora.
                No se asignan cursos ni horarios en esta etapa.
            </p>
            @error('file')<p style="color:#b91c1c;">{{ $message }}</p>@enderror
            @error('campus_id')<p style="color:#b91c1c;">{{ $message }}</p>@enderror
            <div style="margin-top:1.25rem;">
                <button class="btn" type="submit">Vista previa</button>
            </div>
        </form>
    </div>
@else
    <div class="soft-kpi-grid soft-kpi-grid-4" style="margin-bottom:1rem;">
        @include('partials.ui.soft-kpi', ['iconName' => 'users', 'label' => 'Filas', 'value' => count($preview->rows)])
        @include('partials.ui.soft-kpi', ['iconName' => 'check', 'label' => 'Listas para importar', 'value' => $preview->validCount(), 'valueClass' => 'value-ok'])
        @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Con error', 'value' => $preview->errorCount(), 'valueClass' => 'value-danger'])
        @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Archivo', 'value' => $preview->filename, 'valueClass' => 'value-purple'])
    </div>

    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Fila</th>
                    <th>Alumno</th>
                    <th>Nivel</th>
                    <th>Programa</th>
                    <th>Estado</th>
                    <th>Acción</th>
                    <th>Resultado</th>
                </tr>
                </thead>
                <tbody>
                @foreach($preview->rows as $row)
                    <tr>
                        <td>{{ $row->lineNumber }}</td>
                        <td>{{ $row->firstName }} {{ $row->lastName }}</td>
                        <td>{{ $row->nivel }}</td>
                        <td>{{ $row->programName ?? '—' }}</td>
                        <td>{{ $row->status === 'active' ? 'Activo' : 'Inactivo' }}</td>
                        <td>{{ $row->action === 'update' ? 'Actualizar' : 'Crear' }}</td>
                        <td>
                            @if($row->isValid)
                                <span class="badge ok">OK</span>
                                @foreach($row->warnings as $warning)
                                    <div class="page-subtitle">{{ $warning }}</div>
                                @endforeach
                            @else
                                <span class="badge danger">Error</span>
                                @foreach($row->errors as $error)
                                    <div style="color:#b91c1c;font-size:0.85rem;">{{ $error }}</div>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
        @if($preview->validCount() > 0)
            <form method="POST" action="{{ route('students.import.store') }}">
                @csrf
                <button class="btn" type="submit">Importar {{ $preview->validCount() }} alumno(s)</button>
            </form>
        @endif
        <a class="btn secondary" href="{{ route('students.import') }}">Cancelar y subir otro archivo</a>
    </div>
@endif
@endsection
