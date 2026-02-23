<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Student;
use App\Support\AlertEngine;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(): View
    {
        $campusId = request()->user()?->campus_id;
        $chargesQuery = Charge::with('student')->latest();
        $paymentsQuery = Payment::with(['student', 'receipt'])->latest();
        $studentsQuery = Student::orderBy('first_name');

        if ($campusId) {
            $chargesQuery->where('campus_id', $campusId);
            $paymentsQuery->where('campus_id', $campusId);
            $studentsQuery->where('campus_id', $campusId);
        }

        return view('finance.index', [
            'charges' => $chargesQuery->paginate(20),
            'payments' => $paymentsQuery->take(20)->get(),
            'students' => $studentsQuery->get(),
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
            $paidTotal = (float) $charge->payments()->sum('amount');
            $charge->status = $paidTotal >= (float) $charge->amount ? 'paid' : 'partial';
            $charge->save();
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
