{{-- Inscripción en un paso desde la ficha del alumno. --}}
<x-ui.modal id="enroll-modal" title="Inscribir en curso" subtitle="{{ $student->full_name }} · {{ $student->campus?->name }}" size="md"
    :data-open-on-load="($errors->has('group_id') && old('return_to') === 'student') ? '1' : null">
    @if($groups->isEmpty())
        <div class="empty-state-inline">No hay cursos activos disponibles en la sede del alumno.</div>
    @else
        <form method="POST" action="{{ route('enrollments.store') }}" class="form-grid" data-guard-submit>
            @csrf
            <input type="hidden" name="student_id" value="{{ $student->id }}">
            <input type="hidden" name="return_to" value="student">
            <input type="hidden" name="status" value="active">
            @include('partials.ui.group-select', ['groups' => $groups, 'id' => 'enroll-group'])
            <div>
                <label for="enroll-date">Fecha de inscripción</label>
                <input type="date" id="enroll-date" name="enrolled_at" value="{{ old('enrolled_at', now()->toDateString()) }}">
            </div>
            @include('partials.finance.skip-tuition')
            <div class="form-actions ui-modal-foot">
                <button class="btn secondary" type="button" data-modal-close>Cancelar</button>
                <button class="btn" type="submit" data-submit-busy-label="Inscribiendo…">Inscribir</button>
            </div>
        </form>
    @endif
</x-ui.modal>
