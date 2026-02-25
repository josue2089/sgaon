<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Student;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use App\Support\FinanceReconcile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $campusId = request()->user()?->campus_id;
        $chargesQuery = Charge::with(['student', 'payments'])->latest();
        $paymentsQuery = Payment::with(['student', 'receipt'])->latest();
        $studentsQuery = Student::orderBy('first_name');

        if ($campusId) {
            $chargesQuery->where('campus_id', $campusId);
            $paymentsQuery->where('campus_id', $campusId);
            $studentsQuery->where('campus_id', $campusId);
        }

        $focusStudentId = $request->integer('student_id') ?: null;
        if ($focusStudentId) {
            $chargesQuery->where('student_id', $focusStudentId);
            $paymentsQuery->where('student_id', $focusStudentId);
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
            'focusStudentId' => $focusStudentId,
            'criticalOverdueCount' => (int) $criticalOverdueCount,
        ]);
    }

    public function storeCharge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'concept' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:pending,partial,paid,overdue'],
            'notes' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        if ($request->user()?->campus_id && (int) $student->campus_id !== (int) $request->user()->campus_id) {
            abort(403);
        }
        $data['campus_id'] = $student->campus_id;

        $charge = Charge::create($data);
        AuditTrail::log($request, 'finance.charge.create', $charge, $data);
        AlertEngine::evaluateFinanceForStudent((int) $data['student_id']);

        return back()->with('success', 'Cargo creado.');
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'charge_id' => ['nullable', 'exists:charges,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        if ($request->user()?->campus_id && (int) $student->campus_id !== (int) $request->user()->campus_id) {
            abort(403);
        }
        $data['campus_id'] = $student->campus_id;

        $payment = Payment::create($data);

        if (! empty($data['charge_id'])) {
            $charge = Charge::findOrFail((int) $data['charge_id']);
            if ((int) $charge->campus_id !== (int) $data['campus_id']) {
                abort(403);
            }
            if ((int) $charge->student_id !== (int) $data['student_id']) {
                abort(403);
            }
            FinanceReconcile::syncCharge($charge);
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
}
