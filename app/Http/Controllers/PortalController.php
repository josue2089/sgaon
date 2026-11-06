<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\ChargePaymentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\Representative;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\PaymentMethod;
use App\Services\Bcv\ExchangeRateService;
use App\Services\LevelRenewalService;
use App\Support\FinanceReconcile;
use App\Support\GradeAuthorization;
use App\Support\PaymentSubmission;
use App\Support\PrivateFiles;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
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

        $charges = $student->charges()->with(['paymentRequests', 'makeupRequest'])->latest('due_date')->get();
        $balance = FinanceReconcile::outstandingByCurrency($charges);
        $payments = $student->payments()->whereNull('voided_at')->latest('paid_at')->get();
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

        $renewalOffers = app(LevelRenewalService::class)->offersFor($student);

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
        $bcvRate = app(ExchangeRateService::class)->getLatestUsdRate();
        $bcvEurRate = app(ExchangeRateService::class)->getLatestEurRate();

        return view('portal.student', compact('student', 'enrollments', 'attendance', 'charges', 'balance', 'payments', 'makeupRequests', 'eligibleMakeupSessions', 'agenda', 'makeupPaymentInstructions', 'pendingCharges', 'chargePaymentRequests', 'portalGradeEntries', 'renewalOffers', 'paymentMethods', 'bcvRate', 'bcvEurRate'));
    }

    public function enrollInRenewalCourse(Request $request, Course $course, LevelRenewalService $renewals): RedirectResponse
    {
        $student = $this->resolveStudent($request);
        abort_if(! $student, 403);

        return $this->renew($request, $student, $course, $renewals, 'Ya estás inscrito en este curso.');
    }

    public function enrollInRenewalCourseAsRepresentative(Request $request, Student $student, Course $course, LevelRenewalService $renewals): RedirectResponse
    {
        abort_unless(GradeAuthorization::representativeCanAccessStudent($request->user(), $student), 403);

        return $this->renew($request, $student, $course, $renewals, $student->full_name.' ya está inscrito en este curso.');
    }

    private function renew(Request $request, Student $student, Course $course, LevelRenewalService $renewals, string $alreadyMessage): RedirectResponse
    {
        return back()->with('success', $renewals->renewFromPortal($student, $course, $request)
            ? 'Inscripción realizada con éxito. La mensualidad ya aparece en Pagos.'
            : $alreadyMessage);
    }

    public function submitMakeupPayment(Request $request, MakeupRequest $makeupRequest): RedirectResponse
    {
        $student = $this->resolveStudent($request);
        abort_if(! $student || (int) $makeupRequest->student_id !== (int) $student->id, 403);

        if ($makeupRequest->charge && $makeupRequest->charge->paymentRequests()->where('status', ChargePaymentRequest::STATUS_PENDING_VALIDATION)->exists()) {
            return back()->withErrors(['payment_proof' => 'Ya hay un comprobante de esta recuperativa en validación.']);
        }

        $data = $request->validate([
            'payment_notes' => ['nullable', 'string'],
            'medical_support_required' => ['nullable', 'boolean'],
            'payment_proof' => ['required', 'file', 'max:10240', PrivateFiles::PROOF_RULE],
            'medical_support' => ['nullable', 'file', 'max:10240', PrivateFiles::PROOF_RULE],
        ]);

        $medicalSupportRequired = (bool) ($request->boolean('medical_support_required'));
        if ($medicalSupportRequired && ! $request->hasFile('medical_support')) {
            return back()->withErrors([
                'medical_support' => 'Debes cargar el reposo para aplicar la tarifa reducida.',
            ])->withInput();
        }

        $paymentProof = $request->file('payment_proof');
        $proofPath = PrivateFiles::store($paymentProof, 'makeup-requests/'.$makeupRequest->id);
        $makeupRequest->attachments()->create([
            'category' => 'payment_proof',
            'title' => 'Comprobante de pago',
            'file_path' => $proofPath,
            'original_name' => $paymentProof->getClientOriginalName(),
            'mime_type' => $paymentProof->getMimeType(),
            'file_size' => $paymentProof->getSize(),
        ]);

        $makeupRequest->medical_support_required = $medicalSupportRequired;
        $makeupRequest->payment_notes = $data['payment_notes'] ?? null;

        if ($medicalSupportRequired && $request->hasFile('medical_support')) {
            if ($makeupRequest->medical_support_path) {
                PrivateFiles::delete($makeupRequest->medical_support_path);
            }
            $medicalSupport = $request->file('medical_support');
            $medicalPath = PrivateFiles::store($medicalSupport, 'makeup-requests/'.$makeupRequest->id);
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

        $students = Student::query()
            ->whereHas('representatives', fn ($query) => $query->whereKey(Representative::forUser($request->user())->pluck('id')))
            ->orderBy('first_name')
            ->with([
                'enrollments.group.course',
                'charges.paymentRequests',
                'charges.makeupRequest',
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

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
        $bcvRate = app(ExchangeRateService::class)->getLatestUsdRate();
        $bcvEurRate = app(ExchangeRateService::class)->getLatestEurRate();

        $renewals = app(LevelRenewalService::class);
        $renewalOffersByStudentId = $students->mapWithKeys(fn (Student $student) => [$student->id => $renewals->offersFor($student)]);

        return view('portal.representative', compact('representative', 'students', 'repGradeEntriesByStudentId', 'paymentMethods', 'bcvRate', 'bcvEurRate', 'renewalOffersByStudentId'));
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
        $student = Student::query()->find($charge->student_id);
        abort_if(! $student || ! GradeAuthorization::representativeCanAccessStudent($request->user(), $student), 403);

        $this->storeChargePaymentRequest($request, $charge, $student, $representative);

        return back()->with('success', 'Comprobante enviado. Queda pendiente de validación.');
    }

    private function resolveStudent(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        // Primero la ficha vinculada. Sin correo no hay respaldo: where('email', null) traería fichas ajenas. #471
        return Student::where('user_id', $user->id)->first()
            ?? ($user->email ? Student::whereNull('user_id')->where('email', $user->email)->first() : null);
    }

    private function resolveRepresentative(Request $request): ?Representative
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return Representative::where('user_id', $user->id)->first()
            ?? ($user->email ? Representative::whereNull('user_id')->where('email', $user->email)->first() : null);
    }

    private function storeChargePaymentRequest(Request $request, Charge $charge, Student $student, ?Representative $representative): void
    {
        // #436: un solo comprobante en validación por cargo, y la recuperativa se paga desde su propia sección.
        if ($charge->hasPaymentInValidation()) {
            throw ValidationException::withMessages(['payment_proof' => 'Ya enviaste un comprobante para este cargo y está en validación.']);
        }
        if ($charge->voided_at || FinanceReconcile::outstandingForCharge($charge) <= 0) {
            throw ValidationException::withMessages(['payment_proof' => 'Este cargo no tiene saldo pendiente.']);
        }
        if (! $representative && $charge->isMakeup()) {
            throw ValidationException::withMessages(['payment_proof' => 'Esta recuperativa se paga desde la sección Recuperativas.']);
        }

        $request->validate([
            'payment_proof' => ['required', 'file', 'max:10240', PrivateFiles::PROOF_RULE],
        ]);

        $payload = PaymentSubmission::validatedCurrencyPayload($request, $charge);

        $proof = $request->file('payment_proof');
        $proofPath = PrivateFiles::store($proof, 'charge-payment-requests/'.$charge->id);

        ChargePaymentRequest::create([
            'campus_id' => $charge->campus_id,
            'student_id' => $student->id,
            'charge_id' => $charge->id,
            'representative_id' => $representative?->id,
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'original_amount' => $payload['original_amount'],
            'exchange_rate' => $payload['exchange_rate'],
            'exchange_rate_effective_at' => $payload['exchange_rate_effective_at'],
            'payment_method_id' => $payload['payment_method_id'],
            'payment_method' => $payload['payment_method'],
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'proof_path' => $proofPath,
            'proof_original_name' => $proof->getClientOriginalName(),
            'proof_mime_type' => $proof->getMimeType(),
            'proof_file_size' => $proof->getSize(),
            'status' => ChargePaymentRequest::STATUS_PENDING_VALIDATION,
            'submitted_at' => now(),
        ]);
    }
}
