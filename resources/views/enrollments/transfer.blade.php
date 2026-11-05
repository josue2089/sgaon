@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Cambiar de curso</h1>
        <p class="page-subtitle">{{ $enrollment->student?->full_name }} · hoy en {{ $enrollment->group?->course?->name ?? $enrollment->group?->name }}</p>
    </div>
    <a class="btn secondary" href="{{ route('students.show', $enrollment->student_id) }}#academico">Volver a la ficha</a>
</div>

<form method="POST" action="{{ route('enrollments.transfer', $enrollment) }}" class="card stack-sm" data-guard-submit>
    @csrf
    @include('partials.ui.group-select', ['groups' => $groups, 'id' => 'transfer-group', 'fieldLabel' => 'Curso nuevo'])
    <div class="grid-2">
        <div>
            <label for="changed_at">Fecha del cambio</label>
            <input id="changed_at" type="date" name="changed_at" value="{{ old('changed_at', today()->toDateString()) }}" required>
        </div>
        <div>
            <label for="reason">Motivo</label>
            <input id="reason" name="reason" maxlength="255" required value="{{ old('reason') }}" placeholder="Ej. Cambio de horario">
        </div>
    </div>

    <fieldset class="stack-xs">
        <legend><strong>¿Qué hacemos con los cargos de este curso?</strong></legend>
        <label class="checkbox-inline">
            <input type="radio" name="charges" value="{{ \App\Services\EnrollmentChangeService::CHARGES_MOVE }}" @checked(old('charges', \App\Services\EnrollmentChangeService::CHARGES_MOVE) === \App\Services\EnrollmentChangeService::CHARGES_MOVE)>
            Pasarlos al curso nuevo, sin generar otra mensualidad (mismo nivel o cambio de horario)
        </label>
        <label class="checkbox-inline">
            <input type="radio" name="charges" value="{{ \App\Services\EnrollmentChangeService::CHARGES_VOID }}" @checked(old('charges') === \App\Services\EnrollmentChangeService::CHARGES_VOID)>
            Anular los que no tienen pagos y generar la mensualidad del curso nuevo
        </label>
    </fieldset>

    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th scope="col">Cargo</th><th scope="col">Vence</th><th scope="col">Monto</th><th scope="col">Estado</th></tr></thead>
            <tbody>
            @forelse($charges as $charge)
                <tr>
                    <td>{{ $charge->concept }}</td>
                    <td>{{ $charge->due_date?->format('d/m/Y') ?? 'N/D' }}</td>
                    <td>{{ \App\Support\MoneyFormat::formatLedgerAmount((float) $charge->amount, $charge->currencyCode()) }}</td>
                    <td>@include('partials.ui.status', ['status' => $charge->status])</td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty-state-inline">Este curso no tiene cargos.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <button class="btn" type="submit">Cambiar de curso</button>
    </div>
</form>
@endsection
