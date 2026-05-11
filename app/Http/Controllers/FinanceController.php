<?php

namespace App\Http\Controllers;

use App\Mail\ChargePaymentApprovedMail;
use App\Mail\ChargePaymentRejectedMail;
use App\Mail\ChargePendingMail;
use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Period;
use App\Models\Receipt;
use App\Models\Student;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\FinanceReconcile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    private function campusId(Request $request): ?int
    {
        return $request->user()?->isMasterAdmin() ? null : $request->user()?->campus_id;
    }

    public function index(Request $request): View|StreamedResponse
    {
        $campusId = $this->campusId($request);
        $chargesQuery = Charge::with(['student', 'payments', 'course', 'group', 'period', 'enrollment.group.course'])->latest();
        $paymentsQuery = Payment::with(['student', 'receipt', 'allocations.charge'])->latest();
        $studentsQuery = Student::orderBy('first_name');
        $enrollmentsQuery = Enrollment::with(['student', 'group.course'])->latest();
        $periodsQuery = Period::orderBy('code');
        $paymentRequestsQuery = ChargePaymentRequest::query()
            ->with(['student', 'representative', 'charge.course', 'validator'])
            ->latest();

        if ($campusId) {
            $chargesQuery->where('campus_id', $campusId);
            $paymentsQuery->where('campus_id', $campusId);
            $studentsQuery->where('campus_id', $campusId);
            $enrollmentsQuery->where('campus_id', $campusId);
            $periodsQuery->where('campus_id', $campusId);
            $paymentRequestsQuery->where('campus_id', $campusId);
        }

        $focusStudentId = $request->integer('student_id') ?: null;
        if ($focusStudentId) {
            $chargesQuery->where('student_id', $focusStudentId);
            $paymentsQuery->where('student_id', $focusStudentId);
            $paymentRequestsQuery->where('student_id', $focusStudentId);
        }

        $driver = DB::connection()->getDriverName();
        $daysOverdueExpression = $driver === 'sqlite'
            ? "CASE WHEN due_date IS NOT NULL AND due_date < DATE('now') AND status IN ('pending','partial','overdue') THEN CAST((julianday('now') - julianday(due_date)) AS INTEGER) ELSE 0 END"
            : "CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status IN ('pending','partial','overdue') THEN DATEDIFF(CURDATE(), due_date) ELSE 0 END";

        $chargesQuery
            ->select('charges.*')
            ->selectRaw($daysOverdueExpression.' as days_overdue')
            ->orderByDesc('days_overdue')
            ->orderByDesc('amount');

        if ($request->query('export') === 'mora_csv') {
            return response()->streamDownload(function () use ($chargesQuery): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['student', 'concept', 'amount', 'due_date', 'days_overdue', 'status']);
                foreach ($chargesQuery->whereIn('status', ['pending', 'partial', 'overdue'])->get() as $charge) {
                    fputcsv($out, [
                        $charge->student->full_name ?? '',
                        $charge->concept,
                        $charge->amount,
                        optional($charge->due_date)->format('Y-m-d'),
                        (int) ($charge->days_overdue ?? 0),
                        $charge->status,
                    ]);
                }
                fclose($out);
            }, 'mora_report.csv', ['Content-Type' => 'text/csv']);
        }

        $criticalOverdueQuery = Charge::query();
        if ($campusId) {
            $criticalOverdueQuery->where('campus_id', $campusId);
        }
        if ($focusStudentId) {
            $criticalOverdueQuery->where('student_id', $focusStudentId);
        }
        $criticalOverdueCondition = $driver === 'sqlite'
            ? "due_date IS NOT NULL AND due_date < DATE('now') AND status IN ('pending','partial','overdue') AND CAST((julianday('now') - julianday(due_date)) AS INTEGER) >= 30"
            : "due_date IS NOT NULL AND due_date < CURDATE() AND status IN ('pending','partial','overdue') AND DATEDIFF(CURDATE(), due_date) >= 30";

        $criticalOverdueCount = $criticalOverdueQuery
            ->whereRaw($criticalOverdueCondition)
            ->count();

        return view('finance.index', [
            'charges' => $chargesQuery->paginate(20)->withQueryString(),
            'payments' => $paymentsQuery->take(20)->get(),
            'students' => $studentsQuery->get(),
            'enrollments' => $enrollmentsQuery
                ->whereIn('status', ['active', 'inactive', 'completed', 'withdrawn'])
                ->get(),
            'periods' => $periodsQuery->get(),
            'paymentRequests' => $paymentRequestsQuery->take(20)->get(),
            'focusStudentId' => $focusStudentId,
            'criticalOverdueCount' => (int) $criticalOverdueCount,
        ]);
    }

    public function storeCharge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'exists:students,id'],
            'enrollment_id' => ['nullable', 'exists:enrollments,id'],
            'concept' => ['required', 'string', 'max:150'],
            'charge_type' => ['nullable', 'in:tuition,materials,registration,makeup,other'],
            'billing_period_label' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:pending,partial,overdue'],
            'notes' => ['nullable', 'string'],
        ]);

        $enrollment = null;
        if (! empty($data['enrollment_id'])) {
            $enrollment = Enrollment::query()
                ->with(['group.course'])
                ->findOrFail((int) $data['enrollment_id']);
            if ($this->campusId($request) && (int) $enrollment->campus_id !== (int) $this->campusId($request)) {
                abort(403);
            }

            $data['student_id'] = $enrollment->student_id;
            $data['campus_id'] = $enrollment->campus_id;
            $data['group_id'] = $enrollment->group_id;
            $data['course_id'] = $enrollment->group?->course_id;
            $data['period_id'] = $enrollment->group?->course?->period_id;
            $data['origin'] = 'manual';
            $data['billing_period_label'] = $data['billing_period_label'] ?: ($enrollment->group?->course?->period?->code ?? null);
        } else {
            $studentId = (int) ($data['student_id'] ?? 0);
            if (! $studentId) {
                return back()->withErrors(['student_id' => 'Debes seleccionar un alumno o una inscripción.'])->withInput();
            }

            $student = Student::findOrFail($studentId);
            if ($this->campusId($request) && (int) $student->campus_id !== (int) $this->campusId($request)) {
                abort(403);
            }
            $data['campus_id'] = $student->campus_id;
            $data['origin'] = 'manual';
        }

        $charge = Charge::create($data);
        AuditTrail::log($request, 'finance.charge.create', $charge, $data);
        AlertEngine::evaluateFinanceForStudent((int) $data['student_id']);
        $this->emailChargePendingIfPossible($charge->fresh('student.representatives'));

        return back()->with('success', 'Cargo creado.');
    }

    public function showReceipt(Request $request, Receipt $receipt): View
    {
        [$receipt, $payment, $allocations] = $this->resolveReceiptContext($request, $receipt);

        return view('finance.receipt', [
            'receipt' => $receipt,
            'payment' => $payment,
            'allocations' => $allocations,
        ]);
    }

    public function downloadReceiptPdf(Request $request, Receipt $receipt)
    {
        [$receipt, $payment, $allocations] = $this->resolveReceiptContext($request, $receipt);
        $logoDataUri = $this->buildReceiptLogoDataUri();

        $pdf = Pdf::loadView('finance.receipt-pdf', [
            'receipt' => $receipt,
            'payment' => $payment,
            'allocations' => $allocations,
            'logoDataUri' => $logoDataUri,
        ])->setPaper('a4');

        return $pdf->download('recibo-'.$receipt->receipt_number.'.pdf');
    }

    public function studentHistory(Request $request, Student $student): View
    {
        if ($this->campusId($request) && (int) $student->campus_id !== (int) $this->campusId($request)) {
            abort(403);
        }

        $filterData = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $student->load([
            'charges.course',
            'charges.group',
            'charges.period',
            'payments.receipt',
            'payments.allocations.charge.course',
            'payments.allocations.charge.group',
            'payments.allocations.charge.period',
            'payments.charge.course',
            'payments.charge.group',
            'payments.charge.period',
        ]);

        $startDate = ! empty($filterData['start_date']) ? Carbon::parse($filterData['start_date'])->startOfDay() : null;
        $endDate = ! empty($filterData['end_date']) ? Carbon::parse($filterData['end_date'])->endOfDay() : null;

        $student->setRelation('charges', $student->charges
            ->filter(fn (Charge $charge) => $this->dateIsWithinRange($charge->created_at, $startDate, $endDate))
            ->values());

        $student->setRelation('payments', $student->payments
            ->filter(fn (Payment $payment) => $this->dateIsWithinRange(
                $payment->paid_at_datetime ?? $payment->paid_at ?? $payment->created_at,
                $startDate,
                $endDate
            ))
            ->values());

        $timeline = $this->buildStudentFinanceTimeline($student);
        $summary = $this->buildStudentFinanceSummary($student);

        return view('finance.student-history', [
            'student' => $student,
            'timeline' => $timeline,
            'summary' => $summary,
            'filters' => [
                'start_date' => $filterData['start_date'] ?? null,
                'end_date' => $filterData['end_date'] ?? null,
            ],
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'charge_id' => ['nullable', 'exists:charges,id'],
            'charge_ids' => ['nullable', 'array'],
            'charge_ids.*' => ['required', 'exists:charges,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        if ($this->campusId($request) && (int) $student->campus_id !== (int) $this->campusId($request)) {
            abort(403);
        }
        $data['campus_id'] = $student->campus_id;

        $selectedChargeIds = collect($data['charge_ids'] ?? [])
            ->push($data['charge_id'] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $charges = collect();
        if ($selectedChargeIds->isNotEmpty()) {
            $charges = Charge::query()
                ->with(['paymentAllocations', 'payments'])
                ->whereIn('id', $selectedChargeIds)
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();

            if ($charges->count() !== $selectedChargeIds->count()) {
                return back()->withErrors(['charge_ids' => 'Uno o más cargos seleccionados no existen.'])->withInput();
            }

            foreach ($charges as $charge) {
                if ((int) $charge->campus_id !== (int) $data['campus_id']) {
                    abort(403);
                }
                if ((int) $charge->student_id !== (int) $data['student_id']) {
                    abort(403);
                }
            }

            $availableToApply = $charges->sum(fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge));
            if ((float) $data['amount'] > (float) $availableToApply) {
                return back()->withErrors([
                    'amount' => 'El monto excede el saldo disponible de los cargos seleccionados.',
                ])->withInput();
            }
        }

        $paymentPayload = $data;
        if ($selectedChargeIds->count() !== 1) {
            $paymentPayload['charge_id'] = null;
        } else {
            $paymentPayload['charge_id'] = $selectedChargeIds->first();
        }

        $payment = Payment::create($paymentPayload);
        $payment->update([
            'paid_at_datetime' => now(),
            'status' => 'confirmed',
            'received_by' => $request->user()?->id,
        ]);

        if ($charges->isNotEmpty()) {
            $remaining = (float) $data['amount'];
            foreach ($charges as $charge) {
                if ($remaining <= 0) {
                    break;
                }
                $outstanding = FinanceReconcile::outstandingForCharge($charge);
                if ($outstanding <= 0) {
                    continue;
                }

                $applied = min($remaining, $outstanding);
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'charge_id' => $charge->id,
                    'amount_applied' => $applied,
                ]);

                $remaining -= $applied;
            }

            foreach ($charges as $charge) {
                FinanceReconcile::syncCharge($charge);
            }
        }

        Receipt::create([
            'campus_id' => $data['campus_id'],
            'payment_id' => $payment->id,
            'receipt_number' => 'R-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
            'issued_at' => $data['paid_at'],
        ]);
        AuditTrail::log($request, 'finance.payment.create', $payment, $data);
        AlertEngine::evaluateFinanceForStudent((int) $data['student_id']);

        return back()->with('success', 'Pago registrado y recibo generado.');
    }

    public function reviewPaymentRequest(Request $request, ChargePaymentRequest $paymentRequest): RedirectResponse
    {
        if ($this->campusId($request) && (int) $paymentRequest->campus_id !== (int) $this->campusId($request)) {
            abort(403);
        }

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        if ($paymentRequest->status !== ChargePaymentRequest::STATUS_PENDING_VALIDATION) {
            return back()->withErrors(['action' => 'La solicitud ya fue procesada.']);
        }

        if ($data['action'] === 'approve') {
            $charge = Charge::query()
                ->with(['paymentAllocations', 'payments'])
                ->findOrFail($paymentRequest->charge_id);
            $amountToApply = min((float) $paymentRequest->amount, FinanceReconcile::outstandingForCharge($charge));

            if ($amountToApply <= 0) {
                return back()->withErrors(['action' => 'El cargo seleccionado ya no tiene saldo pendiente.']);
            }

            $payment = Payment::create([
                'campus_id' => $paymentRequest->campus_id,
                'student_id' => $paymentRequest->student_id,
                'charge_id' => $charge->id,
                'amount' => $amountToApply,
                'paid_at' => now()->toDateString(),
                'paid_at_datetime' => now(),
                'method' => $paymentRequest->payment_method ?: 'Comprobante portal',
                'reference' => $paymentRequest->reference,
                'status' => 'confirmed',
                'received_by' => $request->user()?->id,
                'notes' => $paymentRequest->notes,
            ]);

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'charge_id' => $charge->id,
                'amount_applied' => $amountToApply,
            ]);
            FinanceReconcile::syncCharge($charge);

            Receipt::create([
                'campus_id' => $paymentRequest->campus_id,
                'payment_id' => $payment->id,
                'receipt_number' => 'R-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
                'issued_at' => now()->toDateString(),
            ]);

            $paymentRequest->forceFill([
                'status' => ChargePaymentRequest::STATUS_APPROVED,
                'validated_by' => $request->user()?->id,
                'validated_at' => now(),
                'rejection_reason' => null,
            ])->save();

            AlertEngine::evaluateFinanceForStudent((int) $paymentRequest->student_id);
            $this->emailPaymentRequestApproved($paymentRequest->fresh(['student.representatives', 'charge']));

            return back()->with('success', 'Solicitud aprobada y pago aplicado.');
        }

        $paymentRequest->forceFill([
            'status' => ChargePaymentRequest::STATUS_REJECTED,
            'validated_by' => $request->user()?->id,
            'validated_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? 'Comprobante inválido.',
        ])->save();
        $this->emailPaymentRequestRejected($paymentRequest->fresh(['student.representatives', 'charge']));

        return back()->with('success', 'Solicitud rechazada.');
    }

    private function emailChargePendingIfPossible(Charge $charge): void
    {
        $recipients = $this->notificationRecipientsForStudent($charge->student);
        if ($recipients->isEmpty()) {
            return;
        }

        Mail::to($recipients->all())->send(new ChargePendingMail($charge));
    }

    private function emailPaymentRequestApproved(ChargePaymentRequest $paymentRequest): void
    {
        if ($paymentRequest->approved_emailed_at) {
            return;
        }
        $recipients = $this->notificationRecipientsForStudent($paymentRequest->student);
        if ($recipients->isEmpty()) {
            return;
        }

        Mail::to($recipients->all())->send(new ChargePaymentApprovedMail($paymentRequest));
        $paymentRequest->forceFill(['approved_emailed_at' => now()])->save();
    }

    private function emailPaymentRequestRejected(ChargePaymentRequest $paymentRequest): void
    {
        if ($paymentRequest->rejected_emailed_at) {
            return;
        }
        $recipients = $this->notificationRecipientsForStudent($paymentRequest->student);
        if ($recipients->isEmpty()) {
            return;
        }

        Mail::to($recipients->all())->send(new ChargePaymentRejectedMail($paymentRequest));
        $paymentRequest->forceFill(['rejected_emailed_at' => now()])->save();
    }

    private function notificationRecipientsForStudent(Student $student)
    {
        $student->loadMissing('representatives');

        return collect([
            $student->email,
            ...$student->representatives->pluck('email')->all(),
        ])->filter(fn ($email) => filled($email))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique()
            ->values();
    }

    private function buildStudentFinanceTimeline(Student $student): Collection
    {
        $chargeItems = $student->charges->map(function (Charge $charge) {
            return [
                'type' => 'charge',
                'date' => $charge->created_at,
                'title' => 'Cargo generado',
                'subtitle' => trim(($charge->course->name ?? 'Sin curso').' · '.($charge->group->name ?? 'Sin grupo')),
                'amount' => (float) $charge->amount,
                'meta' => [
                    'concepto' => $charge->concept,
                    'periodo' => $charge->period->code ?? ($charge->billing_period_label ?: 'Sin período'),
                    'estado' => $charge->status,
                    'saldo' => number_format(FinanceReconcile::outstandingForCharge($charge), 2),
                ],
            ];
        });

        $paymentItems = $student->payments->map(function (Payment $payment) {
            $allocations = $payment->allocations;
            if ($allocations->isEmpty() && $payment->charge) {
                $allocations = collect([
                    (object) [
                        'amount_applied' => $payment->amount,
                        'charge' => $payment->charge,
                    ],
                ]);
            }

            return [
                'type' => 'payment',
                'date' => $payment->paid_at_datetime ?? $payment->paid_at ?? $payment->created_at,
                'title' => 'Pago registrado',
                'subtitle' => $payment->receipt->receipt_number ?? 'Sin recibo',
                'amount' => (float) $payment->amount,
                'meta' => [
                    'metodo' => $payment->method ?: 'Sin método',
                    'referencia' => $payment->reference ?: 'Sin referencia',
                    'cargos' => $allocations->map(function ($allocation) {
                        $charge = $allocation->charge;

                        return trim(($charge?->concept ?? 'Cargo').' · '.number_format((float) ($allocation->amount_applied ?? 0), 2));
                    })->implode(' | '),
                ],
                'receipt_id' => $payment->receipt?->id,
            ];
        });

        return $chargeItems
            ->concat($paymentItems)
            ->sortByDesc(fn (array $item) => optional($item['date'])->timestamp ?? 0)
            ->values();
    }

    private function buildStudentFinanceSummary(Student $student): array
    {
        $totalCharged = (float) $student->charges->sum('amount');
        $totalPaid = (float) $student->payments->sum('amount');
        $outstanding = (float) $student->charges->sum(
            fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge)
        );
        $overdueCount = (int) $student->charges
            ->filter(fn (Charge $charge) => in_array($charge->status, ['overdue', 'partial', 'pending'], true) && $charge->due_date?->isPast())
            ->count();

        return [
            'total_charged' => $totalCharged,
            'total_paid' => $totalPaid,
            'outstanding' => $outstanding,
            'overdue_count' => $overdueCount,
            'payments_count' => (int) $student->payments->count(),
            'charges_count' => (int) $student->charges->count(),
        ];
    }

    private function resolveReceiptContext(Request $request, Receipt $receipt): array
    {
        $receipt->load([
            'payment.student',
            'payment.receivedBy',
            'payment.allocations.charge.course',
            'payment.allocations.charge.group',
            'payment.allocations.charge.period',
            'payment.charge.course',
            'payment.charge.group',
            'payment.charge.period',
        ]);

        if ($this->campusId($request) && (int) $receipt->campus_id !== (int) $this->campusId($request)) {
            abort(403);
        }

        $payment = $receipt->payment;
        $allocations = $payment->allocations;

        if ($allocations->isEmpty() && $payment->charge) {
            $allocations = collect([
                (object) [
                    'amount_applied' => $payment->amount,
                    'charge' => $payment->charge,
                ],
            ]);
        }

        return [$receipt, $payment, $allocations];
    }

    private function buildReceiptLogoDataUri(): ?string
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    private function dateIsWithinRange($date, ?Carbon $startDate, ?Carbon $endDate): bool
    {
        if (! $date instanceof Carbon) {
            return false;
        }

        if ($startDate && $date->lt($startDate)) {
            return false;
        }

        if ($endDate && $date->gt($endDate)) {
            return false;
        }

        return true;
    }
}
