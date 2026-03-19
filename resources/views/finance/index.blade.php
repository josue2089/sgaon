@extends('layouts.app')
@section('content')
@php
    $chargesTotal = $charges->sum('amount');
    $paymentsTotal = $payments->sum('amount');
    $overdueCount = $charges->where('status', 'overdue')->count();
@endphp
<div class="module-head">
    <div>
        <h1 class="page-title">Financiero</h1>
        <p class="page-subtitle">Control de cargos, pagos y cobranza</p>
    </div>
    <a class="btn secondary" href="{{ route('finance.index', array_merge(request()->query(), ['export' => 'mora_csv'])) }}">Exportar mora CSV</a>
    @if($focusStudentId)
        @include('partials.ui.status-badge', ['tone' => 'info', 'text' => 'Enfocado en alumno #'.$focusStudentId])
    @endif
</div>

<div class="soft-kpi-grid soft-kpi-grid-4">
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Cargos (página)', 'value' => '$'.number_format($chargesTotal, 0)])
    @include('partials.ui.soft-kpi', ['iconName' => 'payment', 'label' => 'Pagos recientes', 'value' => '$'.number_format($paymentsTotal, 0)])
    @include('partials.ui.soft-kpi', ['iconName' => 'warning', 'label' => 'Cuentas en mora', 'value' => $overdueCount, 'valueClass' => 'value-danger'])
    @include('partials.ui.soft-kpi', ['iconName' => 'trend', 'label' => 'Mora crítica (30+ días)', 'value' => $criticalOverdueCount, 'valueClass' => 'value-danger'])
</div>

<div class="grid-2">
    <div class="card">
        <h3 class="section-title section-title-sm">Nuevo cargo</h3>
        <form class="stack-sm" method="POST" action="{{ route('finance.charges.store') }}" id="finance-charge-form">
            @csrf
            <div>
                <label>Alumno</label>
                <select name="student_id" id="charge-student-select">
                    <option value="">Derivar por inscripción</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected((int) old('student_id', $focusStudentId) === (int) $student->id)>{{ $student->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Inscripción / curso</label>
                <input type="text" id="charge-enrollment-search" placeholder="Buscar inscripción por alumno, curso o grupo">
                <select name="enrollment_id" id="charge-enrollment-select">
                    <option value="">Sin vínculo académico</option>
                    @foreach($enrollments as $enrollment)
                        <option
                            value="{{ $enrollment->id }}"
                            data-student-id="{{ $enrollment->student_id }}"
                            data-search="{{ strtolower(($enrollment->student->full_name ?? 'alumno').' '.($enrollment->group->course->name ?? 'curso').' '.($enrollment->group->name ?? 'grupo')) }}"
                            @selected((int) old('enrollment_id') === (int) $enrollment->id)
                        >{{ $enrollment->student->full_name ?? 'Alumno' }} · {{ $enrollment->group->course->name ?? 'Curso' }} · {{ $enrollment->group->name ?? 'Grupo' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Concepto</label>
                <input name="concept">
            </div>
            <div>
                <label>Tipo de cargo</label>
                <select name="charge_type">
                    <option value="">Sin clasificar</option>
                    @foreach(['tuition' => 'Mensualidad', 'materials' => 'Materiales', 'registration' => 'Inscripción', 'makeup' => 'Recuperación', 'other' => 'Otro'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('charge_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Periodo de cobro</label>
                <input name="billing_period_label" value="{{ old('billing_period_label') }}" placeholder="Ej. 2026-Q2">
            </div>
            <div>
                <label>Monto</label>
                <input type="number" step="0.01" name="amount">
            </div>
            <div>
                <label>Vencimiento</label>
                <input type="date" name="due_date">
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    @foreach(['pending','partial','paid','overdue'] as $status)
                        <option>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn" type="submit">Crear cargo</button>
        </form>
    </div>

    <div class="card">
        <h3 class="section-title section-title-sm">Registrar pago</h3>
        <form class="stack-sm" method="POST" action="{{ route('finance.payments.store') }}" id="finance-payment-form">
            @csrf
            <div>
                <label>Alumno</label>
                <select name="student_id" id="payment-student-select">
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected((int) old('student_id', $focusStudentId) === (int) $student->id)>{{ $student->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Cargos a aplicar</label>
                <input type="text" id="payment-charge-search" placeholder="Buscar cargo por concepto, curso o período">
                <select name="charge_ids[]" id="payment-charge-select" multiple size="8">
                    @foreach($charges as $charge)
                        @php
                            $balance = \App\Support\FinanceReconcile::outstandingForCharge($charge);
                        @endphp
                        <option
                            value="{{ $charge->id }}"
                            data-student-id="{{ $charge->student_id }}"
                            data-balance="{{ number_format($balance, 2, '.', '') }}"
                            data-search="{{ strtolower(($charge->student->full_name ?? '').' '.$charge->concept.' '.($charge->course->name ?? '').' '.($charge->group->name ?? '').' '.($charge->period->code ?? '')) }}"
                            @selected(in_array((string) $charge->id, array_map('strval', old('charge_ids', old('charge_id') ? [old('charge_id')] : [])), true))
                        >{{ $charge->student->full_name ?? '' }} - {{ $charge->concept }} - Saldo ${{ number_format($balance, 2) }}</option>
                    @endforeach
                </select>
                <div class="form-hint">Puedes seleccionar uno o varios cargos del mismo alumno.</div>
            </div>
            <div>
                <label>Monto</label>
                <input type="number" step="0.01" name="amount" id="payment-amount-input">
            </div>
            <div>
                <label>Fecha</label>
                <input type="date" name="paid_at">
            </div>
            <div>
                <label>Método</label>
                <input name="method">
            </div>
            <div>
                <label>Referencia</label>
                <input name="reference">
            </div>
            <button class="btn" type="submit">Registrar pago</button>
        </form>
    </div>
</div>

<div class="card">
    <h3 class="section-title section-title-sm">Cuentas por cobrar</h3>
    <table>
        <thead>
        <tr><th>Alumno</th><th>Curso</th><th>Grupo</th><th>Periodo</th><th>Concepto</th><th>Monto</th><th>Pagado</th><th>Saldo</th><th>Mora</th><th>Status</th></tr>
        </thead>
        <tbody>
        @forelse($charges as $charge)
            @php
                $daysOverdue = (int) ($charge->days_overdue ?? 0);
                $moraTone = $daysOverdue >= 30 ? 'danger' : ($daysOverdue >= 10 ? 'warn' : ($daysOverdue > 0 ? 'info' : 'ok'));
                $moraText = $daysOverdue > 0 ? $daysOverdue.' día(s)' : 'Al día';
                $paidTotal = \App\Support\FinanceReconcile::paidTotalForCharge($charge);
                $balance = \App\Support\FinanceReconcile::outstandingForCharge($charge);
            @endphp
            <tr>
                <td><a href="{{ route('finance.students.history', $charge->student) }}">{{ $charge->student->full_name ?? '' }}</a></td>
                <td>{{ $charge->course->name ?? 'Sin curso' }}</td>
                <td>{{ $charge->group->name ?? 'Sin grupo' }}</td>
                <td>{{ $charge->period->code ?? ($charge->billing_period_label ?: 'Sin período') }}</td>
                <td>{{ $charge->concept }}</td>
                <td>${{ number_format($charge->amount,2) }}</td>
                <td>${{ number_format($paidTotal,2) }}</td>
                <td>${{ number_format($balance,2) }}</td>
                <td>@include('partials.ui.status-badge', ['tone' => $moraTone, 'text' => $moraText])</td>
                <td><span class="status-pill {{ $charge->status === 'paid' ? 'success' : ($charge->status === 'overdue' ? 'danger' : 'warn') }}">{{ $charge->status }}</span></td>
            </tr>
        @empty
            <tr>
                <td colspan="10">
                    <div class="empty-state-inline">No hay cargos para el contexto seleccionado.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    @if($charges->hasPages())
        {{ $charges->links() }}
    @endif
</div>

<div class="card">
    <h3 class="section-title section-title-sm">Pagos recientes / Recibos</h3>
    <table>
        <thead>
        <tr><th>Alumno</th><th>Monto</th><th>Fecha</th><th>Recibo</th></tr>
        </thead>
        <tbody>
        @forelse($payments as $payment)
            <tr>
                <td><a href="{{ route('finance.students.history', $payment->student) }}">{{ $payment->student->full_name ?? '' }}</a></td>
                <td>${{ number_format($payment->amount,2) }}</td>
                <td>{{ $payment->paid_at?->format('Y-m-d') }}</td>
                <td>
                    @if($payment->receipt)
                        <a href="{{ route('finance.receipts.show', $payment->receipt) }}">{{ $payment->receipt->receipt_number }}</a>
                    @endif
                    @if($payment->allocations->count() > 0)
                        <div class="table-sub">{{ $payment->allocations->count() }} cargo(s)</div>
                    @elseif($payment->charge_id)
                        <div class="table-sub">1 cargo</div>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">
                    <div class="empty-state-inline">Aún no hay pagos registrados.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const chargeStudent = document.getElementById('charge-student-select');
        const chargeEnrollment = document.getElementById('charge-enrollment-select');
        const chargeEnrollmentSearch = document.getElementById('charge-enrollment-search');
        const paymentStudent = document.getElementById('payment-student-select');
        const paymentCharge = document.getElementById('payment-charge-select');
        const paymentChargeSearch = document.getElementById('payment-charge-search');
        const paymentAmount = document.getElementById('payment-amount-input');

        const filterSelect = (select, { studentId = '', term = '' } = {}) => {
            if (!select) return;
            const normalizedTerm = term.trim().toLowerCase();

            Array.from(select.options).forEach((option, index) => {
                if (index === 0 && option.value === '') {
                    option.hidden = false;
                    return;
                }

                const optionStudentId = option.dataset.studentId || '';
                const searchText = option.dataset.search || option.textContent.toLowerCase();
                const studentMatch = !studentId || optionStudentId === String(studentId);
                const termMatch = !normalizedTerm || searchText.includes(normalizedTerm);
                const visible = studentMatch && termMatch;
                option.hidden = !visible;

                if (!visible && option.selected) {
                    option.selected = false;
                    if (!select.multiple) {
                        select.value = '';
                    }
                }
            });
        };

        const syncChargeStudentFromEnrollment = () => {
            if (!chargeStudent || !chargeEnrollment) return;
            const selected = chargeEnrollment.selectedOptions[0];
            if (!selected || !selected.dataset.studentId) return;
            chargeStudent.value = selected.dataset.studentId;
            filterSelect(chargeEnrollment, { studentId: selected.dataset.studentId, term: chargeEnrollmentSearch?.value || '' });
        };

        const syncPaymentStudentFromCharge = () => {
            if (!paymentStudent || !paymentCharge) return;
            const selectedOptions = Array.from(paymentCharge.selectedOptions);
            if (selectedOptions.length === 0) return;

            const studentId = selectedOptions[0].dataset.studentId || '';
            if (studentId) {
                paymentStudent.value = studentId;
                filterSelect(paymentCharge, { studentId, term: paymentChargeSearch?.value || '' });
            }

            if (paymentAmount) {
                const total = selectedOptions.reduce((sum, option) => sum + Number(option.dataset.balance || 0), 0);
                if (total > 0) {
                    paymentAmount.value = total.toFixed(2);
                }
            }
        };

        if (chargeStudent) {
            filterSelect(chargeEnrollment, { studentId: chargeStudent.value, term: chargeEnrollmentSearch?.value || '' });
            chargeStudent.addEventListener('change', () => {
                filterSelect(chargeEnrollment, { studentId: chargeStudent.value, term: chargeEnrollmentSearch?.value || '' });
            });
        }

        if (chargeEnrollmentSearch) {
            chargeEnrollmentSearch.addEventListener('input', () => {
                filterSelect(chargeEnrollment, { studentId: chargeStudent?.value || '', term: chargeEnrollmentSearch.value });
            });
        }

        if (chargeEnrollment) {
            chargeEnrollment.addEventListener('change', syncChargeStudentFromEnrollment);
            if (chargeEnrollment.value) {
                syncChargeStudentFromEnrollment();
            }
        }

        if (paymentStudent) {
            filterSelect(paymentCharge, { studentId: paymentStudent.value, term: paymentChargeSearch?.value || '' });
            paymentStudent.addEventListener('change', () => {
                filterSelect(paymentCharge, { studentId: paymentStudent.value, term: paymentChargeSearch?.value || '' });
            });
        }

        if (paymentChargeSearch) {
            paymentChargeSearch.addEventListener('input', () => {
                filterSelect(paymentCharge, { studentId: paymentStudent?.value || '', term: paymentChargeSearch.value });
            });
        }

        if (paymentCharge) {
            paymentCharge.addEventListener('change', syncPaymentStudentFromCharge);
            if (paymentCharge.selectedOptions.length > 0) {
                syncPaymentStudentFromCharge();
            }
        }
    })();
</script>
@endpush
