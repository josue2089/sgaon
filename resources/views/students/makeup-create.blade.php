@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Registrar clase recuperativa</h1>
        <p class="page-subtitle">{{ $student->full_name }} · La clase queda reservada con el profesor, la fecha y el horario indicados.</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('students.show', $student) }}">Volver al alumno</a>
    </div>
</div>

@if($errors->any())
    <div class="flash err">{{ $errors->first() }}</div>
@endif

@if($enrollments->isEmpty())
    <div class="card empty-state">El alumno no tiene inscripciones activas. Inscríbelo en un curso antes de registrar una recuperativa.</div>
@else
<form method="POST" action="{{ route('students.makeups.store', $student) }}" class="card" data-guard-submit>
    @csrf
    <div class="grid-2">
        <div>
            <label>Clase que está viendo</label>
            <select name="enrollment_id" required>
                @foreach($enrollments as $enrollment)
                    <option value="{{ $enrollment->id }}" @selected((int) old('enrollment_id') === $enrollment->id)>{{ $enrollment->group?->course?->name ?? $enrollment->group?->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Clase que recupera (inasistencia)</label>
            <select name="attendance_record_id">
                <option value="">Sin inasistencia registrada</option>
                @foreach($absences as $absence)
                    <option value="{{ $absence->id }}" @selected((int) old('attendance_record_id') === $absence->id)>
                        {{ $absence->classSession?->session_date?->format('d/m/Y') }} · {{ $absence->enrollment?->group?->course?->name }}{{ $absence->status === 'justified' ? ' (justificada)' : '' }}
                    </option>
                @endforeach
            </select>
            <div class="form-hint">Si eliges una inasistencia que ya tenía su recuperativa pendiente, se usa esa misma y su cargo.</div>
        </div>
        <div>
            <label>Profesor</label>
            <select name="teacher_id" required>
                <option value="">Seleccione</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected((int) old('teacher_id') === $teacher->id)>{{ $teacher->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Fecha de la recuperativa</label>
            <input type="date" name="session_date" value="{{ old('session_date', now()->toDateString()) }}" required>
        </div>
        <div>
            <label>Hora de inicio</label>
            <input type="time" name="starts_at" value="{{ old('starts_at') }}" required>
        </div>
        <div>
            <label>Hora de fin</label>
            <input type="time" name="ends_at" value="{{ old('ends_at') }}" required>
        </div>
        <div>
            <label class="checkbox-inline">
                <input type="checkbox" name="medical_support_required" value="1" data-makeup-medical @checked(old('medical_support_required'))>
                Con reposo médico
            </label>
        </div>
        <div>
            <label>Precio (USD)</label>
            <input type="number" name="price" min="0" step="0.01" value="{{ old('price', 10) }}" data-makeup-price required>
            <div class="form-hint">$10 por defecto, $5 con reposo. Se puede modificar.</div>
        </div>
        <div style="grid-column:1/-1;">
            <label>¿Pagó?</label>
            <select name="paid" data-makeup-paid required>
                <option value="0" @selected(old('paid', '0') === '0')>No, el cargo queda pendiente</option>
                <option value="1" @selected(old('paid') === '1')>Sí, registrar el pago</option>
            </select>
        </div>
    </div>

    <fieldset data-makeup-payment style="border:0;padding:0;margin:1rem 0 0;min-width:0;" @if(old('paid') !== '1') hidden disabled @endif>
        <h3 class="section-title section-title-sm">Pago</h3>
        <div class="grid-2">
            <div>
                <label>Método de pago</label>
                <select name="payment_method_id" data-makeup-method>
                    <option value="">Seleccione</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->id }}" data-currency="{{ $method->currency }}" @selected((int) old('payment_method_id') === $method->id)>{{ $method->label }} ({{ $method->currency }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Moneda</label>
                <select name="currency" data-makeup-currency>
                    @foreach(['USD', 'VES', 'EUR'] as $currency)
                        <option value="{{ $currency }}" @selected(old('currency', 'USD') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Monto pagado (en la moneda del pago)</label>
                <input type="number" name="original_amount" min="0.01" step="0.01" value="{{ old('original_amount', 10) }}">
            </div>
            <div>
                <label>Fecha de pago</label>
                <input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}">
            </div>
            <div style="grid-column:1/-1;">
                <label>Referencia</label>
                <input name="reference" value="{{ old('reference') }}" placeholder="Número de referencia o comprobante">
            </div>
        </div>
    </fieldset>

    <div style="margin-top:1rem;">
        <label>Observaciones</label>
        <textarea name="notes" placeholder="Opcional">{{ old('notes') }}</textarea>
    </div>

    <div class="form-actions" style="margin-top:1rem;">
        <button class="btn" type="submit" data-submit-busy-label="Registrando…">Registrar recuperativa</button>
        <a class="btn secondary" href="{{ route('students.show', $student) }}">Cancelar</a>
    </div>
</form>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const paid = document.querySelector('[data-makeup-paid]');
        const payment = document.querySelector('[data-makeup-payment]');
        const medical = document.querySelector('[data-makeup-medical]');
        const price = document.querySelector('[data-makeup-price]');
        const method = document.querySelector('[data-makeup-method]');
        const currency = document.querySelector('[data-makeup-currency]');
        paid.addEventListener('change', function () {
            const isPaid = paid.value === '1';
            payment.hidden = !isPaid;
            payment.disabled = !isPaid;
        });
        medical.addEventListener('change', function () {
            price.value = medical.checked ? 5 : 10;
        });
        method.addEventListener('change', function () {
            const option = method.selectedOptions[0];
            if (option && option.dataset.currency) {
                currency.value = option.dataset.currency;
            }
        });
    });
</script>
@endif
@endsection
