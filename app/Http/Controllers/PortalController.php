<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\GradeEntry;
use App\Models\Representative;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function student(Request $request): View
    {
        $student = $this->resolveStudent($request);

        abort_if(! $student, 404, 'Perfil de alumno no vinculado.');

        $enrollments = $student->enrollments()->with('group.course')->latest('enrolled_at')->get();
        $attendance = $student->enrollments()
            ->withCount([
                'attendanceRecords as present_count' => fn ($q) => $q->where('status', 'present'),
                'attendanceRecords as absent_count' => fn ($q) => $q->where('status', 'absent'),
                'attendanceRecords as late_count' => fn ($q) => $q->where('status', 'late'),
                'attendanceRecords as justified_count' => fn ($q) => $q->where('status', 'justified'),
            ])->get();

        $charges = $student->charges()->latest('due_date')->get();
        $payments = $student->payments()->latest('paid_at')->get();
        $makeupRequests = $student->makeupRequests()
            ->with([
                'missedSession.group.course.program',
                'missedSession.group.course.programLevel',
                'attachments',
                'booking.makeupSession.teacher',
                'charge',
            ])
            ->latest()
            ->get();

        $eligibleMakeupSessions = collect();
        $bookingEligibleRequests = $makeupRequests->where('status', MakeupRequest::STATUS_APPROVED_FOR_BOOKING);
        if ($bookingEligibleRequests->isNotEmpty()) {
            $programLevelIds = $bookingEligibleRequests
                ->map(fn (MakeupRequest $makeupRequest) => $makeupRequest->enrollment?->group?->course?->program_level_id)
                ->filter()
                ->unique()
                ->values();

            $eligibleMakeupSessions = MakeupSession::query()
                ->with(['teacher', 'program', 'programLevel'])
                ->withCount('activeBookings')
                ->where('campus_id', $student->campus_id)
                ->whereIn('status', ['open', 'full'])
                ->whereDate('session_date', '>=', now()->toDateString())
                ->when($programLevelIds->isNotEmpty(), fn ($query) => $query->whereIn('program_level_id', $programLevelIds))
                ->orderBy('session_date')
                ->orderBy('starts_at')
                ->get()
                ->filter(fn (MakeupSession $session) => $session->available_slots > 0)
                ->values();
        }

        $groupIds = $student->enrollments()
            ->where('status', 'active')
            ->with(['group.course'])
            ->get()
            ->pluck('group_id')
            ->filter()
            ->values();

        $regularAgenda = collect();
        if ($groupIds->isNotEmpty()) {
            $regularAgenda = \App\Models\ClassSession::query()
                ->with('group.course.teacher')
                ->whereIn('group_id', $groupIds)
                ->whereDate('session_date', '>=', now()->toDateString())
                ->orderBy('session_date')
                ->orderBy('starts_at')
                ->limit(20)
                ->get()
                ->map(fn ($session) => [
                    'kind' => 'regular',
                    'date' => $session->session_date,
                    'starts_at' => $session->starts_at,
                    'ends_at' => $session->ends_at,
                    'label' => $session->group?->course?->name ?? 'Clase regular',
                    'teacher' => $session->group?->course?->teacher?->full_name ?? 'N/D',
                ]);
        }

        $makeupAgenda = $makeupRequests
            ->filter(fn (MakeupRequest $makeupRequest) => $makeupRequest->booking && in_array($makeupRequest->booking->status, ['reserved', 'attended'], true))
            ->map(function (MakeupRequest $makeupRequest) {
                $session = $makeupRequest->booking?->makeupSession;

                return [
                    'kind' => 'makeup',
                    'date' => $session?->session_date,
                    'starts_at' => $session?->starts_at,
                    'ends_at' => $session?->ends_at,
                    'label' => 'Recuperativa · '.($makeupRequest->enrollment?->group?->course?->name ?? 'N/D'),
                    'teacher' => $session?->teacher?->full_name ?? 'N/D',
                ];
            });

        $agenda = $regularAgenda
            ->concat($makeupAgenda)
            ->filter(fn (array $item) => ! empty($item['date']))
            ->sortBy(fn (array $item) => optional($item['date'])->timestamp ?? 0)
            ->values();

        $makeupPaymentInstructions = SystemSetting::getValue('makeup_payment_instructions', '');
        $pendingChargeStatuses = ['pending', 'partial', 'overdue'];
        $pendingCharges = $charges
            ->filter(fn (Charge $charge) => in_array($charge->status, $pendingChargeStatuses, true))
            ->values();
        $chargePaymentRequests = $student->chargePaymentRequests()
            ->with(['charge'])
            ->latest()
            ->get();

        $portalGradeEntries = GradeEntry::query()
            ->whereHas('enrollment', fn ($q) => $q->where('student_id', $student->id))
            ->with(['evaluationSet.course'])
            ->get()
            ->sortByDesc(fn ($entry) => optional($entry->evaluationSet)->evaluated_on)
            ->values();

        return view('portal.student', compact('student', 'enrollments', 'attendance', 'charges', 'payments', 'makeupRequests', 'eligibleMakeupSessions', 'agenda', 'makeupPaymentInstructions', 'pendingCharges', 'chargePaymentRequests', 'portalGradeEntries'));
    }

    public function submitMakeupPayment(Request $request, MakeupRequest $makeupRequest): RedirectResponse
    {
        $student = $this->resolveStudent($request);
        abort_if(! $student || (int) $makeupRequest->student_id !== (int) $student->id, 403);

        $data = $request->validate([
            'payment_notes' => ['nullable', 'string'],
            'medical_support_required' => ['nullable', 'boolean'],
            'payment_proof' => ['required', 'file', 'max:10240'],
            'medical_support' => ['nullable', 'file', 'max:10240'],
        ]);

        $paymentProof = $request->file('payment_proof');
        $proofPath = $paymentProof->store('makeup-requests/'.$makeupRequest->id, 'public');
        $makeupRequest->attachments()->create([
            'category' => 'payment_proof',
            'title' => 'Comprobante de pago',
            'file_path' => $proofPath,
            'original_name' => $paymentProof->getClientOriginalName(),
            'mime_type' => $paymentProof->getMimeType(),
            'file_size' => $paymentProof->getSize(),
        ]);

        $medicalSupportRequired = (bool) ($request->boolean('medical_support_required'));
        if ($medicalSupportRequired && ! $request->hasFile('medical_support')) {
            return back()->withErrors([
                'medical_support' => 'Debes cargar el reposo para aplicar la tarifa reducida.',
            ])->withInput();
        }
        $makeupRequest->medical_support_required = $medicalSupportRequired;
        $makeupRequest->payment_notes = $data['payment_notes'] ?? null;

        if ($medicalSupportRequired && $request->hasFile('medical_support')) {
            if ($makeupRequest->medical_support_path) {
                Storage::disk('public')->delete($makeupRequest->medical_support_path);
            }
            $medicalSupport = $request->file('medical_support');
            $medicalPath = $medicalSupport->store('makeup-requests/'.$makeupRequest->id, 'public');
            $makeupRequest->medical_support_path = $medicalPath;
            $makeupRequest->attachments()->create([
                'category' => 'medical_support',
                'title' => 'Reposo médico',
                'file_path' => $medicalPath,
                'original_name' => $medicalSupport->getClientOriginalName(),
                'mime_type' => $medicalSupport->getMimeType(),
                'file_size' => $medicalSupport->getSize(),
            ]);
        }

        $makeupRequest->status = MakeupRequest::STATUS_PENDING_VALIDATION;
        $makeupRequest->save();
        MakeupRecoveryEngine::refreshPriceAndCharge($makeupRequest);

        return back()->with('success', 'Comprobante cargado. Queda pendiente de validación.');
    }

    public function bookMakeupSession(Request $request, MakeupRequest $makeupRequest): RedirectResponse
    {
        $student = $this->resolveStudent($request);
        abort_if(! $student || (int) $makeupRequest->student_id !== (int) $student->id, 403);

        $data = $request->validate([
            'makeup_session_id' => ['required', 'exists:makeup_sessions,id'],
        ]);

        $session = MakeupSession::query()
            ->withCount('activeBookings')
            ->findOrFail((int) $data['makeup_session_id']);

        MakeupRecoveryEngine::book($makeupRequest, $session);

        return back()->with('success', 'Recuperativa reservada.');
    }

    public function representative(Request $request): View
    {
        $representative = $this->resolveRepresentative($request);
        abort_if(! $representative, 404, 'Perfil de representante no vinculado.');

        $students = $representative->students()
            ->with([
                'enrollments.group.course',
                'charges',
                'payments',
                'chargePaymentRequests.charge',
            ])
            ->get();

        $studentIds = $students->pluck('id')->all();
        $repGradeEntriesByStudentId = collect();
        if ($studentIds !== []) {
            $repGradeEntriesByStudentId = GradeEntry::query()
                ->whereHas('enrollment', fn ($q) => $q->whereIn('student_id', $studentIds))
                ->with(['evaluationSet.course', 'enrollment'])
                ->get()
                ->groupBy(fn ($entry) => (int) $entry->enrollment->student_id);
        }

        return view('portal.representative', compact('representative', 'students', 'repGradeEntriesByStudentId'));
    }

    public function submitChargePaymentAsStudent(Request $request, Charge $charge): RedirectResponse
    {
        $student = $this->resolveStudent($request);
        abort_if(! $student || (int) $charge->student_id !== (int) $student->id, 403);

        $this->storeChargePaymentRequest($request, $charge, $student, null);

        return back()->with('success', 'Comprobante enviado. Queda pendiente de validación.');
    }

    public function submitChargePaymentAsRepresentative(Request $request, Charge $charge): RedirectResponse
    {
        $representative = $this->resolveRepresentative($request);
        abort_if(! $representative, 404, 'Perfil de representante no vinculado.');
        $student = $representative->students()->where('students.id', $charge->student_id)->first();
        abort_if(! $student, 403);

        $this->storeChargePaymentRequest($request, $charge, $student, $representative);

        return back()->with('success', 'Comprobante enviado. Queda pendiente de validación.');
    }

    private function resolveStudent(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return Student::where('user_id', $user->id)
            ->orWhere(fn ($q) => $q->whereNull('user_id')->where('email', $user->email))
            ->first();
    }

    private function resolveRepresentative(Request $request): ?Representative
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return Representative::where('user_id', $user->id)
            ->orWhere(fn ($q) => $q->whereNull('user_id')->where('email', $user->email))
            ->first();
    }

    private function storeChargePaymentRequest(Request $request, Charge $charge, Student $student, ?Representative $representative): void
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'payment_proof' => ['required', 'file', 'max:10240'],
        ]);

        $proof = $request->file('payment_proof');
        $proofPath = $proof->store('charge-payment-requests/'.$charge->id, 'public');

        ChargePaymentRequest::create([
            'campus_id' => $charge->campus_id,
            'student_id' => $student->id,
            'charge_id' => $charge->id,
            'representative_id' => $representative?->id,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'proof_path' => $proofPath,
            'proof_original_name' => $proof->getClientOriginalName(),
            'proof_mime_type' => $proof->getMimeType(),
            'proof_file_size' => $proof->getSize(),
            'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION,
            'submitted_at' => now(),
        ]);
    }
}
