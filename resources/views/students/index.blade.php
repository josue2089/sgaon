@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Alumnos</h1>
        <p class="page-subtitle">Gestiona todos los estudiantes</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        @if(auth()->user()?->isMasterAdmin() && \Illuminate\Support\Facades\Route::has('students.export'))
            <a class="btn secondary" href="{{ route('students.export', request()->query()) }}">Exportar CSV</a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('students.import'))
            <a class="btn secondary" href="{{ route('students.import') }}">Importar</a>
        @endif
        <a class="btn" href="{{ route('students.create') }}">Nuevo Alumno</a>
    </div>
</div>

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'users', 'label' => 'Total Alumnos', 'value' => $summary['total']])
    @include('partials.ui.soft-kpi', ['iconName' => 'check', 'label' => 'Activos', 'value' => $summary['active'], 'valueClass' => 'value-ok'])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Inactivos', 'value' => $summary['inactive'], 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Asist. Promedio', 'value' => is_null($summary['attendance_rate']) ? 'N/D' : $summary['attendance_rate'].'%', 'valueClass' => 'value-purple'])
</div>

<form method="GET" action="{{ route('students.index') }}" class="card">
    <div class="fi-filter-bar">
        <div class="search">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre o email...">
        </div>
        <select name="level">
            <option value="">Todos los niveles</option>
            @foreach($levels as $level)
                <option value="{{ $level->code }}" @selected($filters['level'] === $level->code)>{{ $level->code }} - {{ $level->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Solo activos (predeterminado)</option>
            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
            <option value="withdrawn" @selected($filters['status'] === 'withdrawn')>Retirados</option>
            <option value="graduated" @selected($filters['status'] === 'graduated')>Graduados</option>
        </select>
        <select name="payment_status">
            <option value="">Estado de pago</option>
            <option value="paid" @selected($filters['payment_status'] === 'paid')>Al día</option>
            <option value="pending" @selected($filters['payment_status'] === 'pending')>Pendiente</option>
            <option value="overdue" @selected($filters['payment_status'] === 'overdue')>En mora</option>
            <option value="no_charges" @selected($filters['payment_status'] === 'no_charges')>Sin cargos</option>
        </select>
        <button class="btn secondary" type="submit">Filtros</button>
    </div>
</form>

@if($students->count() === 0)
    <div class="card empty-state">No hay alumnos para los filtros seleccionados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Email</th>
                    <th>Sede</th>
                    <th>Nivel</th>
                    <th>Asistencia</th>
                    <th>Pago</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($students as $student)
                    @php
                        $level = optional(optional($student->enrollments->first())->group)->course?->level;
                        $levelCode = $level?->code ?: ($level ? strtoupper(substr((string) $level->name, 0, 2)) : 'N/A');
                        $studentPaymentStatus = $paymentStatusByStudent[$student->id] ?? 'no_charges';
                        $paymentBadge = match($studentPaymentStatus) {
                            'paid' => ['ok', 'Al día'],
                            'pending' => ['warn', 'Pendiente'],
                            'overdue' => ['danger', 'En mora'],
                            default => ['info', 'Sin cargos'],
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="table-user">
                                <span class="table-avatar">
                                    @if($student->profile_photo_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($student->profile_photo_path) }}" alt="{{ $student->full_name }}">
                                    @else
                                        {{ strtoupper(substr($student->first_name, 0, 1)) }}
                                    @endif
                                </span>
                                <div>
                                    <div class="table-title">{{ $student->full_name }}</div>
                                    <div class="table-sub">{{ $student->document_id ?: 'Sin documento' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $student->email ?: 'Sin email' }}</td>
                        <td>{{ $student->campus->name ?? 'Sin sede' }}</td>
                        <td>{{ $levelCode }}</td>
                        <td>{{ isset($attendanceByStudent[$student->id]) ? ((int) $attendanceByStudent[$student->id]).'%' : 'N/D' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $paymentBadge[0], 'text' => $paymentBadge[1]])</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $student->status === 'active' ? 'ok' : 'warn', 'text' => ucfirst($student->status)])</td>
                        <td class="table-actions">
                            <a href="{{ route('students.show', $student) }}">Detalle</a>
                            @if(\Illuminate\Support\Facades\Route::has('students.move-to-historical'))
                                <button
                                    type="button"
                                    class="btn-link"
                                    data-move-historical-open
                                    data-student-name="{{ $student->full_name }}"
                                    data-move-url="{{ route('students.move-to-historical', $student) }}"
                                >
                                    Mover a histórico
                                </button>
                            @endif
                            @if(auth()->user()?->isMasterAdmin())
                                <button
                                    type="button"
                                    class="btn-link-danger"
                                    data-student-delete-open
                                    data-student-name="{{ $student->full_name }}"
                                    data-delete-url="{{ route('students.destroy', $student) }}"
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

@if($students->hasPages())
    <div class="card">{{ $students->links() }}</div>
@endif

@if(\Illuminate\Support\Facades\Route::has('students.move-to-historical'))
    <div class="student-picker-modal confirm-modal" data-move-historical-modal hidden>
        <div class="student-picker-backdrop" data-move-historical-close></div>
        <div class="student-picker-dialog confirm-modal-dialog card">
            <div class="student-picker-head">
                <div>
                    <h3 class="section-title">Mover a histórico</h3>
                    <p class="page-subtitle">El alumno dejará de aparecer en el listado de activos.</p>
                </div>
                <button class="btn secondary" type="button" data-move-historical-close>Cerrar</button>
            </div>
            <form method="POST" action="#" data-move-historical-form>
                @csrf
                <div class="form-grid">
                    <label>
                        Nuevo estado
                        <select name="status" required>
                            <option value="inactive">Inactivo</option>
                            <option value="graduated">Graduado</option>
                            <option value="withdrawn">Retirado</option>
                        </select>
                    </label>
                </div>
                <p style="margin-top:1rem;">Alumno: <strong data-move-historical-name>—</strong></p>
                <div class="form-actions">
                    <button class="btn secondary" type="button" data-move-historical-close>Cancelar</button>
                    <button class="btn" type="submit">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-move-historical-modal]');
            const form = document.querySelector('[data-move-historical-form]');
            const nameNode = document.querySelector('[data-move-historical-name]');
            const openButtons = Array.from(document.querySelectorAll('[data-move-historical-open]'));
            const closeButtons = Array.from(document.querySelectorAll('[data-move-historical-close]'));

            if (!modal || !form || !nameNode) {
                return;
            }

            const openModal = (button) => {
                form.action = button.dataset.moveUrl || '#';
                nameNode.textContent = button.dataset.studentName || '—';
                modal.hidden = false;
                document.body.style.overflow = 'hidden';
            };

            const closeModal = () => {
                modal.hidden = true;
                document.body.style.overflow = '';
            };

            openButtons.forEach((button) => button.addEventListener('click', () => openModal(button)));
            closeButtons.forEach((button) => button.addEventListener('click', closeModal));
            modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) closeModal(); });
        })();
    </script>
@endif

@if(auth()->user()?->isMasterAdmin())
    <div class="student-picker-modal confirm-modal" data-student-delete-modal hidden>
        <div class="student-picker-backdrop" data-student-delete-close></div>
        <div class="student-picker-dialog confirm-modal-dialog card">
            <div class="student-picker-head">
                <div>
                    <h3 class="section-title">Eliminar alumno</h3>
                    <p class="page-subtitle">Esta acción no se puede deshacer.</p>
                </div>
                <button class="btn secondary" type="button" data-student-delete-close>Cerrar</button>
            </div>
            <div class="confirm-modal-body">
                <p>
                    Vas a eliminar permanentemente a <strong data-student-delete-name>este alumno</strong>.
                </p>
                <p>
                    Se borrará toda su información en el sistema, incluyendo inscripciones, asistencia,
                    cargos, pagos, representantes vinculados, adjuntos y demás datos relacionados.
                </p>
            </div>
            <form method="POST" action="#" data-student-delete-form>
                @csrf
                @method('DELETE')
                <div class="form-actions">
                    <button class="btn secondary" type="button" data-student-delete-close>Cancelar</button>
                    <button class="btn btn-danger" type="submit">Eliminar permanentemente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-student-delete-modal]');
            const form = document.querySelector('[data-student-delete-form]');
            const nameNode = document.querySelector('[data-student-delete-name]');
            const openButtons = Array.from(document.querySelectorAll('[data-student-delete-open]'));
            const closeButtons = Array.from(document.querySelectorAll('[data-student-delete-close]'));

            if (!modal || !form || !nameNode || openButtons.length === 0) {
                return;
            }

            const openModal = (button) => {
                form.action = button.dataset.deleteUrl || '#';
                nameNode.textContent = button.dataset.studentName || 'este alumno';
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
