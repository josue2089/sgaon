@php
    $formId = $formId ?? 'finance-payment-form';
    $prefix = $prefix ?? 'finance-payment';
    $lockStudent = $lockStudent ?? false;
    $charges = $charges ?? collect();
    $students = $students ?? collect();
    $focusStudentId = $focusStudentId ?? null;
    $studentId = $studentId ?? null;
@endphp
<form
    class="stack-sm"
    method="POST"
    action="{{ $formAction }}"
    id="{{ $formId }}"
    data-finance-payment-form
    @if($lockStudent) data-student-locked="true" @endif
    @if($lockStudent && $studentId) data-student-id="{{ $studentId }}" @endif
>
    @csrf
    @unless($lockStudent)
        <div class="searchable-select searchable-select--combo" data-searchable-select data-searchable-combo>
            <label>Alumno</label>
            <input type="text" id="{{ $formId }}-student-search" class="searchable-select__search" placeholder="Buscar alumno por nombre, cédula o representante..." autocomplete="off">
            <select name="student_id" id="{{ $formId }}-student-select" class="searchable-select__list" required>
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
        <label>Cargos a aplicar</label>
        <input type="text" id="{{ $formId }}-charge-search" class="searchable-select__search" placeholder="Buscar cargo por concepto, curso o período">
        <select name="charge_ids[]" id="{{ $formId }}-charge-select" class="searchable-select__list" multiple>
            @foreach($charges as $charge)
                @php
                    $balance = \App\Support\FinanceReconcile::outstandingForCharge($charge);
                @endphp
                <option
                    value="{{ $charge->id }}"
                    data-student-id="{{ $charge->student_id }}"
                    data-balance="{{ \App\Support\MoneyFormat::raw($balance) }}"
                    data-currency="{{ $charge->currencyCode() }}"
                    data-search="{{ strtolower(($charge->student->full_name ?? '').' '.$charge->concept.' '.($charge->course->name ?? '').' '.($charge->group->name ?? '').' '.($charge->period->code ?? '')) }}"
                    @selected(in_array((string) $charge->id, array_map('strval', old('charge_ids', old('charge_id') ? [old('charge_id')] : [])), true))
                >{{ $charge->student->full_name ?? '' }} — {{ $charge->concept }} — Saldo {{ \App\Support\MoneyFormat::chargeAmount($charge, $charge->isEur() ? ($bcvEurRate['rate'] ?? 0) : ($bcvRate['rate'] ?? 0)) }}</option>
            @endforeach
        </select>
        <div class="form-hint">Puedes seleccionar uno o varios cargos del mismo alumno.</div>
    </div>
    <div data-payment-currency-root="{{ $prefix }}">
        @include('partials.payment-currency-fields', [
            'prefix' => $prefix,
            'chargeCurrency' => 'USD',
            'balanceAmount' => 0,
            'usdExchangeRate' => $bcvRate['rate'] ?? 0,
            'eurExchangeRate' => $bcvEurRate['rate'] ?? 0,
            'paymentMethods' => $paymentMethods,
        ])
    </div>
    <div>
        <label>Nueva fecha de vencimiento del saldo</label>
        <input type="date" name="balance_due_date" value="{{ old('balance_due_date') }}">
        <div class="form-hint">Opcional. Úsala cuando el pago deje saldo pendiente.</div>
    </div>
    <div>
        <label>Fecha</label>
        <input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}">
    </div>
    <div>
        <label>Referencia</label>
        <input name="reference" value="{{ old('reference') }}">
    </div>
    <button class="btn" type="submit">Registrar pago</button>
</form>
