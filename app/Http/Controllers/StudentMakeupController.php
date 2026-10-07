<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesCampusAccess;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\MakeupRequest;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\PaymentRegistrationService;
use App\Support\AuditTrail;
use App\Support\MakeupRecoveryEngine;
use App\Support\PaymentCurrencyConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Registro manual de clases recuperativas desde la ficha del alumno.
 */
class StudentMakeupController extends Controller
{
    use ScopesCampusAccess;

    public function create(Student $student): View
    {
        $this->authorizeCampus($student->campus_id);

        return view('students.makeup-create', [
            'student' => $student,
            'enrollments' => $this->enrollments($student),
            'absences' => $this->pendingAbsences($student),
            'teachers' => Teacher::query()->where('campus_id', $student->campus_id)->where('status', 'active')->orderBy('first_name')->orderBy('last_name')->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeCampus($student->campus_id);

        $data = $request->validate([
            'enrollment_id' => ['required', Rule::exists('enrollments', 'id')->where('student_id', $student->id)],
            'attendance_record_id' => ['nullable', 'exists:attendance_records,id'],
            'teacher_id' => ['required', Rule::exists('teachers', 'id')->where('campus_id', $student->campus_id)],
            'session_date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'medical_support_required' => ['nullable', 'boolean'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000'],
            'paid' => ['required', 'boolean'],
            'payment_method_id' => ['required_if:paid,1', 'nullable', 'exists:payment_methods,id'],
            'currency' => ['required_if:paid,1', 'nullable', Rule::in([PaymentCurrencyConverter::CURRENCY_USD, PaymentCurrencyConverter::CURRENCY_VES, PaymentCurrencyConverter::CURRENCY_EUR])],
            'original_amount' => ['required_if:paid,1', 'nullable', 'numeric', 'min:0.01'],
            'paid_at' => ['required_if:paid,1', 'nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $enrollment = Enrollment::query()->findOrFail($data['enrollment_id']);
        $absence = null;
        if (! empty($data['attendance_record_id'])) {
            $absence = AttendanceRecord::query()->findOrFail($data['attendance_record_id']);
            if ((int) $absence->enrollment?->student_id !== (int) $student->id) {
                abort(404);
            }
        }

        try {
            $makeup = MakeupRecoveryEngine::registerManual($enrollment, $absence, [
                'teacher_id' => (int) $data['teacher_id'],
                'session_date' => $data['session_date'],
                'starts_at' => $data['starts_at'].':00',
                'ends_at' => $data['ends_at'].':00',
                'price' => (float) $data['price'],
                'medical_support_required' => (bool) ($data['medical_support_required'] ?? false),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($data['paid'] && $makeup->charge && $makeup->charge->status !== 'paid') {
                app(PaymentRegistrationService::class)->register([
                    'student_id' => $student->id,
                    'charge_ids' => [$makeup->charge_id],
                    'currency' => $data['currency'],
                    'original_amount' => $data['original_amount'],
                    'payment_method_id' => $data['payment_method_id'],
                    'paid_at' => $data['paid_at'],
                    'reference' => $data['reference'] ?? null,
                    'notes' => 'Pago de clase recuperativa registrada desde la ficha del alumno.',
                ], $request);
            }
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        AuditTrail::log($request, 'makeup.manual.create', $makeup, [
            'enrollment_id' => $enrollment->id,
            'attendance_record_id' => $absence?->id,
            'teacher_id' => $data['teacher_id'],
            'session_date' => $data['session_date'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'price' => $data['price'],
            'paid' => (bool) $data['paid'],
        ]);

        return redirect()->route('students.show', $student)->with('success', $data['paid']
            ? 'Clase recuperativa registrada y pago aplicado.'
            : 'Clase recuperativa registrada. El cargo quedó pendiente de pago.');
    }

    private function enrollments(Student $student)
    {
        return $student->enrollments()
            ->with('group.course')
            ->where('status', 'active')
            ->orderByDesc('enrolled_at')
            ->get();
    }

    /**
     * Inasistencias que todavía no tienen una recuperativa reservada o completada.
     */
    private function pendingAbsences(Student $student)
    {
        return AttendanceRecord::query()
            ->with(['classSession', 'enrollment.group.course'])
            ->whereIn('status', [AttendanceRecord::STATUS_ABSENT, AttendanceRecord::STATUS_JUSTIFIED])
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))
            ->whereDoesntHave('makeupRequest', fn ($query) => $query->whereIn('status', [MakeupRequest::STATUS_BOOKED, MakeupRequest::STATUS_COMPLETED, MakeupRequest::STATUS_CANCELLED]))
            ->get()
            ->sortByDesc(fn (AttendanceRecord $record) => $record->classSession?->session_date)
            ->values();
    }
}
