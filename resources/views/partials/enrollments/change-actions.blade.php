{{-- Acciones sobre una inscripción activa: retirar (modal) y cambiar de curso. #441 --}}
@if($enrollment->status === 'active')
    @php($modalId = 'withdraw-enrollment-'.$enrollment->id)
    <a href="{{ route('enrollments.transfer.edit', $enrollment) }}">Cambiar de curso</a>
    <button class="btn-link-danger" type="button" data-modal-open="{{ $modalId }}">Retirar</button>
    {{-- Fuera de la tabla: dentro de .table-actions heredaría su maquetación. --}}
    @push('modals')
    <x-ui.modal :id="$modalId" title="Retirar del curso" :subtitle="($enrollment->student?->full_name ?? 'Alumno').' · '.($enrollment->group?->course?->name ?? $enrollment->group?->name ?? '')" size="md">
        <form method="POST" action="{{ route('enrollments.withdraw', $enrollment) }}" class="stack-sm" data-guard-submit>
            @csrf
            <div>
                <label for="{{ $modalId }}-date">Fecha del retiro</label>
                <input id="{{ $modalId }}-date" type="date" name="withdrawn_at" value="{{ today()->toDateString() }}" required>
            </div>
            <div>
                <label for="{{ $modalId }}-reason">Motivo</label>
                <input id="{{ $modalId }}-reason" name="reason" maxlength="255" required placeholder="Ej. Se muda de ciudad">
            </div>
            <label class="checkbox-inline">
                <input type="checkbox" name="void_future_charges" value="1" checked>
                Anular los cargos sin pagar que vencen después de esta fecha
            </label>
            <p class="entity-sub">Los cargos ya vencidos y los pagados no se tocan. En cursos extracurriculares, las cuotas futuras sin pagar se anulan siempre.</p>
            <div class="form-actions">
                <button class="btn secondary" type="button" data-modal-close>Cancelar</button>
                <button class="btn" type="submit">Retirar</button>
            </div>
        </form>
    </x-ui.modal>
    @endpush
@endif
