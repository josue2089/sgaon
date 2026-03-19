@extends('layouts.app')
@section('content')
@php
    $selectedCourse = $selected['course'];
    $selectedGroup = $selected['group'];
    $selectedSession = $selected['session'];
    $enrolledStudentIds = $selected['enrolled_student_ids'] ?? collect();
@endphp

<div class="module-head">
    <div>
        <h1 class="page-title">Flujo MVP ⚙️</h1>
        <p class="page-subtitle">Crea Curso → Grupo → Sesión → Inscripciones en una sola vista</p>
    </div>
    <div class="badge-pill badge-info">Operativo</div>
</div>

<div class="card">
    <div class="fi-filter-bar">
        <div class="badge-pill {{ $selectedCourse ? 'badge-ok' : 'badge-warn' }}">1. Curso {{ $selectedCourse ? 'listo' : 'pendiente' }}</div>
        <div class="badge-pill {{ $selectedGroup ? 'badge-ok' : 'badge-warn' }}">2. Grupo {{ $selectedGroup ? 'listo' : 'pendiente' }}</div>
        <div class="badge-pill {{ $selectedSession ? 'badge-ok' : 'badge-warn' }}">3. Sesión {{ $selectedSession ? 'lista' : 'pendiente' }}</div>
        <div class="badge-pill {{ $selectedGroup && $enrolledStudentIds->count() > 0 ? 'badge-ok' : 'badge-warn' }}">4. Inscripciones {{ $enrolledStudentIds->count() > 0 ? 'listas' : 'pendiente' }}</div>
    </div>
</div>

<div class="entity-grid">
    <div class="entity-card">
        <h3>Paso 1 · Crear curso</h3>
        <form method="POST" action="{{ route('operations.wizard.course') }}">
            @csrf
            <div class="grid-2">
                <div>
                    <label>Campus</label>
                    <select name="campus_id" required>
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Nivel</label>
                    <select name="academic_level_id" required>
                        @foreach($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->code ? $level->code.' - ' : '' }}{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Nombre</label>
                    <input name="name" required placeholder="Ej. B2 Intermedio Alto">
                </div>
                <div>
                    <label>Código</label>
                    <input name="code" placeholder="Ej. B2-IA">
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="active">active</option>
                        <option value="inactive">inactive</option>
                    </select>
                </div>
                <div>
                    <label>Descripción</label>
                    <input name="description" placeholder="Descripción breve">
                </div>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Guardar curso</button>
            </div>
        </form>
    </div>

    <div class="entity-card">
        <h3>Paso 2 · Crear grupo</h3>
        <form method="POST" action="{{ route('operations.wizard.group') }}">
            @csrf
            <div class="grid-2">
                <div>
                    <label>Curso</label>
                    <select name="course_id" required>
                        <option value="">Seleccione</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}" @selected($selectedCourse && $selectedCourse->id === $course->id)>{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Profesor</label>
                    <select name="teacher_id">
                        <option value="">Sin asignar</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Nombre del grupo</label>
                    <input name="name" required placeholder="Ej. B2-M-003">
                </div>
                <div>
                    <label>Periodo</label>
                    <select name="period">
                        <option value="">Seleccione</option>
                        @foreach($periodOptions as $period)
                            <option value="{{ $period }}">{{ $period }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Horario</label>
                    <select name="schedule">
                        <option value="">Seleccione</option>
                        @foreach($scheduleOptions as $schedule)
                            <option value="{{ $schedule }}">{{ $schedule }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        @foreach($statusOptions as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Inicio</label>
                    <input type="date" name="start_date">
                </div>
                <div>
                    <label>Fin</label>
                    <input type="date" name="end_date">
                </div>
                <div>
                    <label>Capacidad</label>
                    <input type="number" min="1" max="200" name="capacity" placeholder="Ej. 20">
                </div>
            </div>
            @if(empty($periodOptions) || empty($scheduleOptions))
                <p class="form-hint">Si faltan opciones en período u horario, deben crearse primero en los catálogos de admin master.</p>
            @endif
            <div class="form-actions">
                <button class="btn" type="submit">Guardar grupo</button>
            </div>
        </form>
    </div>

    <div class="entity-card">
        <h3>Paso 3 · Crear sesión</h3>
        <form method="POST" action="{{ route('operations.wizard.session') }}">
            @csrf
            <div class="grid-2">
                <div>
                    <label>Grupo</label>
                    <select name="group_id" required>
                        <option value="">Seleccione</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" @selected($selectedGroup && $selectedGroup->id === $group->id)>{{ $group->name }}{{ $group->course ? ' · '.$group->course->name : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Fecha</label>
                    <input type="date" name="session_date" required value="{{ now()->format('Y-m-d') }}">
                </div>
                <div>
                    <label>Inicio</label>
                    <input type="time" name="starts_at">
                </div>
                <div>
                    <label>Fin</label>
                    <input type="time" name="ends_at">
                </div>
                <div>
                    <label>Tema</label>
                    <input name="topic" placeholder="Tema de la clase">
                </div>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Guardar sesión</button>
            </div>
        </form>
    </div>
</div>

<div class="card" style="margin-top:1rem;">
    <h3>Paso 4 · Inscribir alumnos al grupo</h3>
    <form method="POST" action="{{ route('operations.wizard.enrollment') }}">
        @csrf
        <div class="grid-2">
            <div>
                <label>Grupo</label>
                <select name="group_id" required>
                    <option value="">Seleccione</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" @selected($selectedGroup && $selectedGroup->id === $group->id)>{{ $group->name }}{{ $group->course ? ' · '.$group->course->name : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Status inscripción</label>
                <select name="status">
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                    <option value="completed">completed</option>
                    <option value="withdrawn">withdrawn</option>
                </select>
            </div>
            <div>
                <label>Fecha inscripción</label>
                <input type="date" name="enrolled_at" value="{{ now()->format('Y-m-d') }}">
            </div>
            <div>
                <label>Progreso (%)</label>
                <input type="number" min="0" max="100" name="progress" value="0">
            </div>
            <div style="grid-column:1/-1;">
                <label>Selecciona alumnos</label>
                <select name="student_ids[]" multiple size="8" required>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected($enrolledStudentIds->contains($student->id))>{{ $student->full_name }}{{ $student->email ? ' · '.$student->email : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div style="grid-column:1/-1;">
                <label>Notas</label>
                <textarea name="notes" placeholder="Notas opcionales para el lote"></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Guardar inscripciones</button>
        </div>
    </form>
</div>

<div class="card" style="margin-top:1rem;">
    <h3>Contexto actual</h3>
    <div class="stack-sm">
        <div><strong>Curso:</strong> {{ $selectedCourse?->name ?? 'No seleccionado' }}</div>
        <div><strong>Grupo:</strong> {{ $selectedGroup?->name ?? 'No seleccionado' }}</div>
        <div><strong>Sesión:</strong> {{ $selectedSession ? $selectedSession->session_date?->format('Y-m-d').' · '.($selectedSession->topic ?: 'Sin tema') : 'No seleccionada' }}</div>
        <div><strong>Alumnos inscritos en grupo:</strong> {{ $enrolledStudentIds->count() }}</div>
    </div>
    <div class="form-actions">
        @if($selectedCourse)
            <a class="btn secondary" href="{{ route('courses.show', $selectedCourse) }}">Ver curso</a>
            <a class="btn secondary" href="{{ route('courses.edit', $selectedCourse) }}">Editar curso</a>
        @endif
        @if($selectedSession)
            <a class="btn secondary" href="{{ route('attendance.index', ['class_session_id' => $selectedSession->id]) }}">Abrir asistencia</a>
        @endif
        @if($selectedGroup)
            <a class="btn secondary" href="{{ route('enrollments.index', ['group_id' => $selectedGroup->id]) }}">Ver inscripciones</a>
        @endif
    </div>
</div>
@endsection
