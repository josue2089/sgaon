@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Totales por sede 🏫</h1>
        <p class="page-subtitle">Alumnos, grupos y docentes por cada sucursal</p>
    </div>
    <a class="btn secondary" href="{{ route('reports.campus-totals', ['export' => 'csv']) }}">Exportar CSV</a>
</div>

<div class="card table-card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sede</th>
                    <th>Código</th>
                    <th>Alumnos activos</th>
                    <th>Alumnos totales</th>
                    <th>Grupos activos</th>
                    <th>Grupos totales</th>
                    <th>Docentes</th>
                    <th>Cursos</th>
                    <th>Inscripciones activas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="table-title">{{ $row['campus']->name }}</td>
                        <td>{{ $row['campus']->code }}</td>
                        <td>{{ $row['students_active'] }}</td>
                        <td>{{ $row['students_total'] }}</td>
                        <td>{{ $row['groups_active'] }}</td>
                        <td>{{ $row['groups_total'] }}</td>
                        <td>{{ $row['teachers_total'] }}</td>
                        <td>{{ $row['courses_total'] }}</td>
                        <td>{{ $row['enrollments_active'] }}</td>
                        <td class="table-actions">
                            <a href="{{ route('campuses.show', $row['campus']) }}">Ver ficha</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="empty-state">No hay sedes disponibles para tu acceso.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="2">Totales</th>
                        <th>{{ $rows->sum('students_active') }}</th>
                        <th>{{ $rows->sum('students_total') }}</th>
                        <th>{{ $rows->sum('groups_active') }}</th>
                        <th>{{ $rows->sum('groups_total') }}</th>
                        <th>{{ $rows->sum('teachers_total') }}</th>
                        <th>{{ $rows->sum('courses_total') }}</th>
                        <th>{{ $rows->sum('enrollments_active') }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
