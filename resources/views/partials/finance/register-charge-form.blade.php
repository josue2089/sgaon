@php
    $formId = $formId ?? 'finance-charge-form';
    $lockStudent = $lockStudent ?? false;
    $studentId = $studentId ?? null;
    $students = $students ?? collect();
    $enrollments = $enrollments ?? collect();
    $focusStudentId = $focusStudentId ?? null;
@endphp
<form
    class="stack-sm"
    method="POST"
    action="{{ $formAction }}"
    id="{{ $formId }}"
    data-finance-charge-form
    @if($lockStudent) data-student-locked="true" @endif
    @if($lockStudent && $studentId) data-student-id="{{ $studentId }}" @endif
>
    @csrf
    @if($lockStudent && $studentId)
        <input type="hidden" name="student_id" value="{{ $studentId }}">
    @endif
    @unless($lockStudent)
        <div class="searchable-select searchable-select--combo" data-searchable-select data-searchable-combo>
            <label for="{{ $formId }}-student-search">Alumno</label>
            <input type="text" id="{{ $formId }}-student-search" class="searchable-select__search" placeholder="Buscar alumno por nombre, cédula o representante..." autocomplete="off">
            <select name="student_id" id="{{ $formId }}-student-select" class="searchable-select__list">
                <option value="" data-search="">Derivar por inscripción</option>
                @foreach($students as $studentOption)
                    <option
                        value="{{ $studentOption->id }}"
                        data-search="{{ \App\Support\StudentSearch::haystack($studentOption) }}"
                        @selected((int) old('student_id', $focusStudentId) === (int) $studentOption->id)
                    >{{ $studentOption->full_name }}{{ $studentOption->email ? ' · '.$studentOption->email : '' }}{{ $studentOption->document_id ? ' · '.$studentOption->document_id : '' }}</option>
                @endforeach
            </select>
        </div>
    @endunless
    <div class="searchable-select" data-searchable-select>
        <label for="{{ $formId }}-enrollment-search">Inscripción / curso</label>
        <input type="text" id="{{ $formId }}-enrollment-search" class="searchable-select__search" placeholder="Buscar inscripción por alumno, cédula, representante, curso o grupo">
        <select name="enrollment_id" id="{{ $formId }}-enrollment-select" class="searchable-select__list">
            <option value="">Sin vínculo académico</option>
            @foreach($enrollments as $enrollment)
                <option
                    value="{{ $enrollment->id }}"
                    data-student-id="{{ $enrollment->student_id }}"
                    data-search="{{ \App\Support\StudentSearch::enrollmentHaystack($enrollment) }}"
                    @selected((int) old('enrollment_id') === (int) $enrollment->id)
                >{{ $enrollment->student->full_name ?? 'Alumno' }} · {{ $enrollment->group->course->name ?? 'Curso' }} · {{ $enrollment->group->name ?? 'Grupo' }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $formId }}-concept">Concepto</label>
        <input id="{{ $formId }}-concept" name="concept" value="{{ old('concept') }}" required>
    </div>
    <div>
        <label for="{{ $formId }}-charge-type">Tipo de cargo</label>
        <select id="{{ $formId }}-charge-type" name="charge_type">
            <option value="">Sin clasificar</option>
            @foreach(['tuition' => 'Mensualidad', 'materials' => 'Materiales', 'registration' => 'Inscripción', 'makeup' => 'Recuperación', 'other' => 'Otro'] as $value => $label)
                <option value="{{ $value }}" @selected(old('charge_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $formId }}-billing-period-label">Periodo de cobro</label>
        <input id="{{ $formId }}-billing-period-label" name="billing_period_label" value="{{ old('billing_period_label') }}" placeholder="Ej. 2026-Q2">
    </div>
    <div>
        <label for="{{ $formId }}-amount">Monto</label>
        <input id="{{ $formId }}-amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required>
    </div>
    <div>
        <label for="{{ $formId }}-currency">Moneda</label>
        <select id="{{ $formId }}-currency" name="currency">
            <option value="USD" @selected(old('currency', 'USD') === 'USD')>USD</option>
            <option value="EUR" @selected(old('currency') === 'EUR')>EUR</option>
        </select>
    </div>
    <div>
        <label for="{{ $formId }}-due-date">Vencimiento</label>
        <input id="{{ $formId }}-due-date" type="date" name="due_date" value="{{ old('due_date') }}">
    </div>
    <div>
        <label for="{{ $formId }}-status">Estado</label>
        <select id="{{ $formId }}-status" name="status">
            @foreach(['pending','partial','overdue'] as $status)
                <option value="{{ $status }}" @selected(old('status', 'pending') === $status)>{{ \App\Support\StatusLabel::label($status) }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn" type="submit">Crear cargo</button>
</form>
