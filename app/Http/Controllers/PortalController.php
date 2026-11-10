<?php

namespace App\Http\Controllers;

use App\Models\Campus;
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
use App\Models\Receipt;
use App\Services\Bcv\ExchangeRateService;
use App\Services\LevelRenewalService;
use App\Services\ReceiptPdfService;
use App\Support\FinanceReconcile;
use App\Support\GradeAuthorization;
use App\Support\PaymentSubmission;
use App\Support\PortalStudentData;
use App\Support\StudentLogbook;
use App\Support\PrivateFiles;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function student(Request $request): View
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->unlinked($request, 'student');
        }

        $data = PortalStudentData::for($student);
        $enrollments = $student->enrollments()->with('group.course')->latest('enrolled_at')->get();
        $portalGradeEntries = GradeEntry::query()
            ->whereHas('enrollment', fn ($q) => $q->where('student_id', $student->id))
            ->with(['evaluationSet.course'])
            ->get()
            ->sortByDesc(fn ($entry) => optional($entry->evaluationSet)->evaluated_on)
            ->values();
        $renewalOffers = app(LevelRenewalService::class)->offersFor($student);

        return view('portal.student', $data + $this->paymentContext() + compact('student', 'enrollments', 'portalGradeEntries', 'renewalOffers'));
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

        return $this->storeMakeupPayment($request, $makeupRequest);
    }

    public function submitMakeupPaymentAsRepresentative(Request $request, MakeupRequest $makeupRequest): RedirectResponse
    {
        $this->authorizeRepresentativeFor($request, $makeupRequest->student);

        return $this->storeMakeupPayment($request, $makeupRequest);
    }

    private function storeMakeupPayment(Request $request, MakeupRequest $makeupRequest): RedirectResponse
    {
        if (! in_array($makeupRequest->status, [MakeupRequest::STATUS_PENDING_PAYMENT, MakeupRequest::STATUS_REJECTED], true)) {
            return back()->withErrors(['payment_proof' => 'Esta recuperativa ya tiene un comprobante en validación o ya fue validada.']);
        }

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

        return $this->storeMakeupBooking($request, $makeupRequest);
    }

    public function bookMakeupSessionAsRepresentative(Request $request, MakeupRequest $makeupRequest): RedirectResponse
    {
        $this->authorizeRepresentativeFor($request, $makeupRequest->student);

        return $this->storeMakeupBooking($request, $makeupRequest);
    }

    private function storeMakeupBooking(Request $request, MakeupRequest $makeupRequest): RedirectResponse
    {
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
        if (! $representative) {
            return $this->unlinked($request, 'representative');
        }

        $students = Student::query()
            ->whereHas('representatives', fn ($query) => $query->whereKey(Representative::forUser($request->user())->pluck('id')))
            ->orderBy('first_name')
            ->with(['enrollments.group.course'])
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

        $renewals = app(LevelRenewalService::class);
        $renewalOffersByStudentId = $students->mapWithKeys(fn (Student $student) => [$student->id => $renewals->offersFor($student)]);
        // #448: por cada hijo, lo mismo que ve el alumno en su portal (agenda, asistencia, recuperativas, pagos y recibos).
        $portalDataByStudentId = $students->mapWithKeys(fn (Student $student) => [$student->id => PortalStudentData::for($student)]);

        return view('portal.representative', $this->paymentContext() + compact('representative', 'students', 'repGradeEntriesByStudentId', 'renewalOffersByStudentId', 'portalDataByStudentId'));
    }

    /** Recibo en PDF de un pago del propio alumno. #448 */
    public function downloadReceiptAsStudent(Request $request, Receipt $receipt): StreamedResponse
    {
        $student = $this->resolveStudent($request);
        abort_if(! $student || (int) $receipt->payment?->student_id !== (int) $student->id, 403);

        return $this->streamReceipt($receipt);
    }

    /** Recibo en PDF de un pago de uno de sus hijos. #448 */
    public function downloadReceiptAsRepresentative(Request $request, Receipt $receipt): StreamedResponse
    {
        $this->authorizeRepresentativeFor($request, $receipt->payment?->student);

        return $this->streamReceipt($receipt);
    }

    /** Bitácora File A/B en PDF. #470 */
    public function downloadLogbookAsStudent(Request $request, Enrollment $enrollment)
    {
        $student = $this->resolveStudent($request);
        abort_if(! $student || (int) $enrollment->student_id !== (int) $student->id, 403);

        return StudentLogbook::download($enrollment);
    }

    public function downloadLogbookAsRepresentative(Request $request, Enrollment $enrollment)
    {
        $this->authorizeRepresentativeFor($request, $enrollment->student);

        return StudentLogbook::download($enrollment);
    }

    private function streamReceipt(Receipt $receipt): StreamedResponse
    {
        abort_if($receipt->payment?->voided_at, 404);
        $pdf = app(ReceiptPdfService::class);

        return response()->streamDownload(fn () => print ($pdf->renderBinary($receipt)), $pdf->filename($receipt), ['Content-Type' => 'application/pdf']);
    }

    private function authorizeRepresentativeFor(Request $request, ?Student $student): void
    {
        abort_if(! $student || ! GradeAuthorization::representativeCanAccessStudent($request->user(), $student), 403);
    }

    /** Métodos de pago y tasas BCV para el formulario del comprobante. */
    private function paymentContext(): array
    {
        $rates = app(ExchangeRateService::class);

        return [
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->orderBy('label')->get(),
            'bcvRate' => $rates->getLatestUsdRate(),
            'bcvEurRate' => $rates->getLatestEurRate(),
            'makeupPaymentInstructions' => SystemSetting::getValue('makeup_payment_instructions', ''),
        ];
    }

    /** La cuenta existe pero no está vinculada a una ficha: página con el contacto de la sede en lugar de un 404. #448 */
    private function unlinked(Request $request, string $kind): View
    {
        return view('portal.unlinked', [
            'kind' => $kind,
            'campus' => Campus::query()->find($request->user()?->campus_id),
        ]);
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
        abort_if(! $representative, 403);
        $student = Student::query()->find($charge->student_id);
        $this->authorizeRepresentativeFor($request, $student);

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
