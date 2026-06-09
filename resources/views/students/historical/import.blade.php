@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Importar alumnos históricos</h1>
        <p class="page-subtitle">Formato ancho (Picacho/Cascada) o ledger 13-16</p>
    </div>
    <a class="btn secondary" href="{{ route('students.historical.index') }}">Volver a históricos</a>
</div>

@if(session('error'))
    <div class="card" style="margin-bottom:1rem;color:#b91c1c;">{{ session('error') }}</div>
@endif

@if(!$preview)
    <div class="card">
        <form method="POST" action="{{ route('students.historical.import.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                @if($isMaster)
                    <label>
                        Sede por defecto
                        <select name="campus_id">
                            <option value="">Detectar por SEDE DE ORIGEN</option>
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
                Formatos soportados: consulta histórica ancha (encabezado fila 6) y ledger 13-16 Picacho (encabezado fila 5).
                Se importan ficha, fecha de inscripción, programa y representante cuando aplique.
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
        @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Formato', 'value' => $preview->format, 'valueClass' => 'value-purple'])
    </div>

    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Fila</th>
                    <th>Hoja</th>
                    <th>Alumno</th>
                    <th>Inscripción</th>
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
                        <td>{{ $row->sheetName }}</td>
                        <td>{{ $row->firstName }} {{ $row->lastName }}</td>
                        <td>{{ $row->enrollmentDate ? \Carbon\Carbon::parse($row->enrollmentDate)->format('d/m/Y') : '—' }}</td>
                        <td>{{ $row->programName ?? ($row->levelCode ?: '—') }}</td>
                        <td>{{ ucfirst($row->status) }}</td>
                        <td>{{ $row->action === 'update' ? 'Actualizar' : 'Crear' }}</td>
                        <td>
                            @if(!$row->isValid)
                                <span style="color:#b91c1c;">{{ implode(' ', $row->errors) }}</span>
                            @elseif($row->warnings !== [])
                                <span style="color:#b45309;">{{ implode(' ', $row->warnings) }}</span>
                            @else
                                <span style="color:#166534;">OK</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('students.historical.import.store') }}" style="margin-top:1rem;">
        @csrf
        <button class="btn" type="submit" @disabled($preview->validCount() === 0)>Confirmar importación ({{ $preview->validCount() }} filas)</button>
        <a class="btn secondary" href="{{ route('students.historical.import') }}">Cancelar</a>
    </form>
@endif
@endsection
