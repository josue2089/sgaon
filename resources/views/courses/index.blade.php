@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Cursos</h1>
        <p class="page-subtitle">Panel operativo de cursos, planificación y avance académico</p>
    </div>
    <a class="btn" href="{{ route('courses.create') }}">Nuevo Curso</a>
</div>

<div class="metric-grid metric-grid-4">
    @include('partials.ui.metric-card', ['tone' => 'metric-blue', 'iconName' => 'book', 'label' => 'Total Cursos', 'value' => $stats['courses']])
    @include('partials.ui.metric-card', ['tone' => 'metric-green', 'iconName' => 'users', 'label' => 'Total Estudiantes', 'value' => $stats['students']])
    @include('partials.ui.metric-card', ['tone' => 'metric-purple', 'iconName' => 'calendar', 'label' => 'Horas Académicas', 'value' => is_null($stats['planned_hours']) ? 'N/D' : $stats['planned_hours'].' h']) 
    @include('partials.ui.metric-card', ['tone' => 'metric-orange', 'iconName' => 'award', 'label' => 'Niveles CEFR', 'value' => $levelStats->count()])
</div>

<form method="GET" action="{{ route('courses.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar curso o código...">
        </div>
        <select name="level">
            <option value="">Todos los niveles</option>
            @foreach($levels as $level)
                <option value="{{ $level->code }}" @selected($filters['level'] === $level->code)>{{ $level->code }} - {{ $level->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Todos</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
        </select>
        <button class="btn secondary" type="submit">Filtros</button>
    </div>
</form>

@if($courses->count() === 0)
    <div class="card empty-state">No hay cursos para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Curso</th>
                    <th>Profesor</th>
                    <th>Horario</th>
                    <th>Período</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Estudiantes</th>
                    <th>Sesiones</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($courses as $course)
                    @php
                        $groupId = $course->managed_group_id;
                        $studentsCount = $groupId ? (int) ($studentsByGroup[$groupId] ?? 0) : 0;
                        $completedCount = $groupId ? (int) ($completedSessionsByGroup[$groupId] ?? 0) : 0;
                        $plannedCount = $groupId ? (int) ($plannedSessionsByGroup[$groupId] ?? 0) : 0;
                    @endphp
                    <tr>
                        <td>
                            <div class="table-title">{{ $course->name }}</div>
                            <div class="table-sub">{{ $course->code ?: 'Sin código' }}</div>
                        </td>
                        <td>{{ $course->teacher?->full_name ?? 'Sin asignar' }}</td>
                        <td>{{ $course->scheduleTemplate?->display_label ?? 'Sin horario' }}</td>
                        <td>{{ $course->period?->code ?? 'Sin período' }}</td>
                        <td>{{ $course->start_date?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td>{{ $course->end_date?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td>{{ $studentsCount }}</td>
                        <td>{{ $completedCount }}/{{ $plannedCount }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $course->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($course->status)])</td>
                        <td class="table-actions">
                            <a href="{{ route('courses.show', $course) }}">Detalle</a>
                            <a href="{{ route('courses.edit', $course) }}">Editar</a>
                            @if(auth()->user()?->isMasterAdmin())
                                <button
                                    type="button"
                                    class="btn-link-danger"
                                    data-course-delete-open
                                    data-course-name="{{ $course->name }}"
                                    data-delete-url="{{ route('courses.destroy', $course) }}"
                                >
                                    Eliminar
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($courses->hasPages())
    <div class="card">{{ $courses->links() }}</div>
@endif

@if(auth()->user()?->isMasterAdmin())
    <div class="student-picker-modal confirm-modal" data-course-delete-modal hidden>
        <div class="student-picker-backdrop" data-course-delete-close></div>
        <div class="student-picker-dialog confirm-modal-dialog card">
            <div class="student-picker-head">
                <div>
                    <h3 class="section-title">Eliminar curso</h3>
                    <p class="page-subtitle">Esta acción no se puede deshacer.</p>
                </div>
                <button class="btn secondary" type="button" data-course-delete-close>Cerrar</button>
            </div>
            <div class="confirm-modal-body">
                <p>
                    Vas a eliminar permanentemente el curso <strong data-course-delete-name>este curso</strong>.
                </p>
                <p>
                    Se borrará toda la información relacionada: grupo operativo, sesiones, asistencia,
                    inscripciones de ese curso, evaluaciones y demás registros vinculados.
                </p>
                <p>
                    Los alumnos <strong>no</strong> se eliminan del sistema; solo se quita su vínculo con este curso.
                </p>
            </div>
            <form method="POST" action="#" data-course-delete-form>
                @csrf
                @method('DELETE')
                <div class="form-actions">
                    <button class="btn secondary" type="button" data-course-delete-close>Cancelar</button>
                    <button class="btn btn-danger" type="submit">Eliminar permanentemente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-course-delete-modal]');
            const form = document.querySelector('[data-course-delete-form]');
            const nameNode = document.querySelector('[data-course-delete-name]');
            const openButtons = Array.from(document.querySelectorAll('[data-course-delete-open]'));
            const closeButtons = Array.from(document.querySelectorAll('[data-course-delete-close]'));

            if (!modal || !form || !nameNode || openButtons.length === 0) {
                return;
            }

            const openModal = (button) => {
                form.action = button.dataset.deleteUrl || '#';
                nameNode.textContent = button.dataset.courseName || 'este curso';
                modal.hidden = false;
                document.body.style.overflow = 'hidden';
            };

            const closeModal = () => {
                modal.hidden = true;
                document.body.style.overflow = '';
            };

            openButtons.forEach((button) => {
                button.addEventListener('click', () => openModal(button));
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            });
        })();
    </script>
@endif
@endsection
