<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\AuthorizedContact;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\Program;
use App\Models\Representative;
use App\Models\StudentAttachment;
use App\Models\Student;
use App\Support\AuditTrail;
use App\Support\FinanceReconcile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StudentController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->isMasterAdmin() ? null : request()->user()?->campus_id;
    }

    public function index(Request $request): View
    {
        $query = Student::query()
            ->with(['campus', 'enrollments.group.course.level'])
            ->latest();

        $baseStatsQuery = Student::query();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
            $baseStatsQuery->where('campus_id', $this->campusId());
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"])
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $levelFilter = (string) $request->query('level', '');
        if ($levelFilter !== '') {
            $query->whereHas('enrollments.group.course.level', function (Builder $builder) use ($levelFilter) {
                $builder
                    ->where('code', $levelFilter)
                    ->orWhere('name', $levelFilter);
            });
        }

        $paymentStatus = (string) $request->query('payment_status', '');
        if ($paymentStatus === 'overdue') {
            $query->whereHas('charges', fn (Builder $builder) => $builder->where('status', 'overdue'));
        } elseif ($paymentStatus === 'pending') {
            $query->whereHas('charges', fn (Builder $builder) => $builder->whereIn('status', ['pending', 'partial']));
        } elseif ($paymentStatus === 'paid') {
            $query
                ->whereHas('charges')
                ->whereDoesntHave('charges', fn (Builder $builder) => $builder->whereIn('status', ['pending', 'partial', 'overdue']));
        } elseif ($paymentStatus === 'no_charges') {
            $query->whereDoesntHave('charges');
        }

        $students = $query->paginate(20)->withQueryString();

        $studentIds = $students->getCollection()->pluck('id')->values();
        $attendanceByStudent = collect();
        if ($studentIds->isNotEmpty()) {
            $attendanceByStudent = AttendanceRecord::query()
                ->join('enrollments', 'enrollments.id', '=', 'attendance_records.enrollment_id')
                ->whereIn('enrollments.student_id', $studentIds)
                ->selectRaw(
                    "enrollments.student_id, ROUND((SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100) as attendance_rate"
                )
                ->groupBy('enrollments.student_id')
                ->pluck('attendance_rate', 'enrollments.student_id');
        }

        $chargesByStudent = collect();
        if ($studentIds->isNotEmpty()) {
            $chargesByStudent = Charge::query()
                ->whereIn('student_id', $studentIds)
                ->select('student_id', 'status')
                ->get()
                ->groupBy('student_id');
        }

        $paymentStatusByStudent = $students->getCollection()->mapWithKeys(function (Student $student) use ($chargesByStudent) {
            $statuses = collect($chargesByStudent->get($student->id, []))->pluck('status');
            if ($statuses->isEmpty()) {
                return [$student->id => 'no_charges'];
            }
            if ($statuses->contains('overdue')) {
                return [$student->id => 'overdue'];
            }
            if ($statuses->contains(fn ($status) => in_array($status, ['pending', 'partial'], true))) {
                return [$student->id => 'pending'];
            }

            return [$student->id => 'paid'];
        });

        $attendanceRate = Enrollment::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->where('enrollments.status', 'active')
            ->join('attendance_records', 'attendance_records.enrollment_id', '=', 'enrollments.id')
            ->selectRaw(
                "ROUND((SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100) as attendance_rate"
            )
            ->value('attendance_rate');

        $levels = AcademicLevel::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name']);

        return view('students.index', [
            'students' => $students,
            'attendanceByStudent' => $attendanceByStudent,
            'paymentStatusByStudent' => $paymentStatusByStudent,
            'summary' => [
                'total' => (clone $baseStatsQuery)->count(),
                'active' => (clone $baseStatsQuery)->where('status', 'active')->count(),
                'inactive' => (clone $baseStatsQuery)->where('status', '!=', 'active')->count(),
                'attendance_rate' => is_null($attendanceRate) ? null : (int) $attendanceRate,
            ],
            'filters' => [
                'q' => $q,
                'level' => $levelFilter,
                'status' => $status,
                'payment_status' => $paymentStatus,
            ],
            'levels' => $levels,
        ]);
    }

    public function create(): View
    {
        $campuses = Campus::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
        }

        $programs = Program::query()->where('status', 'active')->orderBy('name');

        return view('students.create', [
            'student' => new Student(),
            'campuses' => $campuses->get(),
            'programs' => $programs->get(),
        ]);
    }

    public function show(Student $student): View
    {
        if ($this->campusId() && (int) $student->campus_id !== (int) $this->campusId()) {
            abort(403);
        }

        return view('students.show', $this->detailData($student));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $data['phone'] = ($data['phone'] ?? null) ?: ($data['mobile_phone'] ?? null);

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo_path'] = $request->file('profile_photo')->store('profiles/students', 'public');
        }

        $student = Student::create($data);
        $this->syncRepresentative($student, $request->input('representative', []));
        $this->syncAuthorizedContacts($student, $request->input('authorized_contacts', []));
        AuditTrail::log($request, 'student.create', $student, $data);

        return redirect()->route('students.index')->with('success', 'Alumno creado.');
    }

    public function edit(Student $student): View
    {
        $campuses = Campus::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
        }

        $programs = Program::query()->where('status', 'active')->orderBy('name')->get();

        return view('students.edit', [
            'student' => $student->load(['representatives', 'authorizedContacts']),
            'campuses' => $campuses->get(),
            'programs' => $programs,
            'auditLogs' => AuditLog::query()
                ->with('user')
                ->where('auditable_type', Student::class)
                ->where('auditable_id', $student->id)
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validatedData($request);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $data['phone'] = ($data['phone'] ?? null) ?: ($data['mobile_phone'] ?? null);

        if ($request->hasFile('profile_photo')) {
            if ($student->profile_photo_path) {
                Storage::disk('public')->delete($student->profile_photo_path);
            }
            $data['profile_photo_path'] = $request->file('profile_photo')->store('profiles/students', 'public');
        }

        $student->update($data);
        $this->syncRepresentative($student, $request->input('representative', []));
        $this->syncAuthorizedContacts($student, $request->input('authorized_contacts', []));
        AuditTrail::log($request, 'student.update', $student, $data);

        return redirect()->route('students.index')->with('success', 'Alumno actualizado.');
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        if (! $request->user()?->isMasterAdmin()) {
            abort(403);
        }

        if ($student->profile_photo_path) {
            Storage::disk('public')->delete($student->profile_photo_path);
        }
        AuditTrail::log($request, 'student.delete', $student, [
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
        ]);
        $student->delete();

        return redirect()->route('students.index')->with('success', 'Alumno eliminado.');
    }

    public function storeAttachment(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:80'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('student-attachments/'.$student->id, 'public');

        $attachment = $student->attachments()->create([
            'title' => $data['title'],
            'category' => $data['category'] ?: 'general',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        AuditTrail::log($request, 'student.attachment.create', $attachment, [
            'title' => $attachment->title,
            'category' => $attachment->category,
            'original_name' => $attachment->original_name,
        ]);

        return redirect()->route('students.show', $student)->with('success', 'Adjunto cargado.');
    }

    public function downloadAttachment(Request $request, Student $student, StudentAttachment $attachment)
    {
        $this->authorizeStudent($student);
        if ((int) $attachment->student_id !== (int) $student->id) {
            abort(404);
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->original_name);
    }

    public function destroyAttachment(Request $request, Student $student, StudentAttachment $attachment): RedirectResponse
    {
        $this->authorizeStudent($student);
        if ((int) $attachment->student_id !== (int) $student->id) {
            abort(404);
        }

        Storage::disk('public')->delete($attachment->file_path);
        AuditTrail::log($request, 'student.attachment.delete', $attachment, [
            'title' => $attachment->title,
            'original_name' => $attachment->original_name,
        ]);
        $attachment->delete();

        return redirect()->route('students.show', $student)->with('success', 'Adjunto eliminado.');
    }

    public function enrollmentSheet(Student $student): View
    {
        $this->authorizeStudent($student);

        return view('students.enrollment-sheet', $this->detailData($student) + [
            'logoDataUri' => $this->buildLogoDataUri(),
        ]);
    }

    public function enrollmentSheetPdf(Student $student)
    {
        $this->authorizeStudent($student);

        $pdf = Pdf::loadView('students.enrollment-sheet-pdf', $this->detailData($student) + [
            'logoDataUri' => $this->buildLogoDataUri(),
        ])->setPaper('a4');

        return $pdf->download('ficha-inscripcion-'.$student->id.'.pdf');
    }

    private function detailData(Student $student): array
    {
        $student->load([
            'campus',
            'registrationProgram',
            'representatives',
            'authorizedContacts',
            'attachments',
            'alerts' => fn ($builder) => $builder->where('status', 'open')->latest(),
            'enrollments' => fn ($builder) => $builder
                ->withCount([
                    'attendanceRecords',
                    'attendanceRecords as present_attendance_count' => fn ($query) => $query->where('status', 'present'),
                ])
                ->with(['group.course.program', 'group.course.programLevel', 'group.course.courseLevel', 'group.course.level', 'group.course.teacher', 'group.course.period', 'group.course.scheduleTemplate'])
                ->orderByDesc('enrolled_at')
                ->orderByDesc('id'),
            'charges' => fn ($builder) => $builder
                ->with(['course.program', 'course.programLevel', 'course.courseLevel', 'group', 'period'])
                ->latest(),
            'payments' => fn ($builder) => $builder
                ->with(['receipt', 'allocations.charge.course', 'charge.course'])
                ->orderByDesc('paid_at_datetime')
                ->orderByDesc('paid_at')
                ->orderByDesc('id'),
        ]);

        $currentEnrollment = $student->enrollments->firstWhere('status', 'active') ?: $student->enrollments->first();
        $currentCourse = $currentEnrollment?->group?->course;
        $currentCourseLevel = $currentCourse?->programLevel ?: $currentCourse?->courseLevel;
        $nextCourseLevel = $currentCourseLevel?->nextLevel();
        $attendanceRate = null;

        if ($currentEnrollment && (int) $currentEnrollment->attendance_records_count > 0) {
            $attendanceRate = (int) round(($currentEnrollment->present_attendance_count / $currentEnrollment->attendance_records_count) * 100);
        }

        $summary = [
            'current_level_label' => $currentCourseLevel
                ? (($currentCourseLevel->sort_order ?? $currentCourseLevel->scale_position).'/'.($currentCourseLevel->program_total ?? $currentCourseLevel->scale_total))
                : 'N/D',
            'current_level_name' => $currentCourseLevel?->name ?? 'Sin nivel asignado',
            'next_level_name' => $nextCourseLevel?->name ?? 'N/D',
            'completion_date' => $currentCourse?->end_date,
            'reminder_date' => ($currentCourse?->end_date && $currentCourseLevel)
                ? $currentCourse->end_date->copy()->subDays((int) $currentCourseLevel->reminder_days_before)
                : null,
            'attendance_rate' => $attendanceRate,
            'charged_total' => (float) $student->charges->sum('amount'),
            'paid_total' => (float) $student->payments->sum('amount'),
            'outstanding_total' => (float) $student->charges->sum(fn (Charge $charge) => FinanceReconcile::outstandingForCharge($charge)),
        ];

        $gradeEvaluationHistory = GradeEntry::query()
            ->whereHas('enrollment', fn ($q) => $q->where('student_id', $student->id))
            ->with(['evaluationSet.course.teacher'])
            ->get()
            ->sortByDesc(fn ($entry) => optional($entry->evaluationSet)->evaluated_on)
            ->values();

        return [
            'student' => $student,
            'currentEnrollment' => $currentEnrollment,
            'currentCourse' => $currentCourse,
            'currentCourseLevel' => $currentCourseLevel,
            'nextCourseLevel' => $nextCourseLevel,
            'summary' => $summary,
            'courseHistory' => $student->enrollments,
            'gradeEvaluationHistory' => $gradeEvaluationHistory,
            'paymentHistory' => $student->payments->take(15),
            'chargeHistory' => $student->charges->take(15),
            'auditLogs' => AuditLog::query()
                ->with('user')
                ->where('auditable_type', Student::class)
                ->where('auditable_id', $student->id)
                ->latest()
                ->limit(12)
                ->get(),
        ];
    }

    private function authorizeStudent(Student $student): void
    {
        if ($this->campusId() && (int) $student->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
    }

    private function buildLogoDataUri(): ?string
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

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'registration_program_id' => ['nullable', 'exists:programs,id'],
            'contract_number' => ['nullable', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_id' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['nullable', 'date'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'landline_phone' => ['nullable', 'string', 'max:40'],
            'mobile_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'family_in_institution' => ['nullable', 'boolean'],
            'family_in_institution_details' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string'],
            'enrollment_date' => ['nullable', 'date'],
            'profile_photo' => ['nullable', 'image', 'max:5120'],
            'medical_has_allergies' => ['nullable', 'boolean'],
            'medical_allergy_details' => ['nullable', 'string'],
            'medical_has_treatment' => ['nullable', 'boolean'],
            'medical_treatment_details' => ['nullable', 'string'],
            'medical_fever_medication' => ['nullable', 'string'],
            'medical_headache_medication' => ['nullable', 'string'],
            'medical_notes' => ['nullable', 'string'],
            'salesperson' => ['nullable', 'string', 'max:120'],
            'promotion' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['nullable', 'string', 'max:120'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:48'],
            'commercial_notes' => ['nullable', 'string'],
            'representative.first_name' => ['nullable', 'string', 'max:120'],
            'representative.last_name' => ['nullable', 'string', 'max:120'],
            'representative.document_id' => ['nullable', 'string', 'max:80'],
            'representative.address' => ['nullable', 'string', 'max:255'],
            'representative.phone' => ['nullable', 'string', 'max:40'],
            'representative.home_phone' => ['nullable', 'string', 'max:40'],
            'representative.mobile_phone' => ['nullable', 'string', 'max:40'],
            'representative.work_place' => ['nullable', 'string', 'max:120'],
            'representative.work_address' => ['nullable', 'string', 'max:255'],
            'representative.email' => ['nullable', 'email'],
            'representative.office_phone' => ['nullable', 'string', 'max:40'],
            'authorized_contacts' => ['array'],
            'authorized_contacts.*.first_name' => ['nullable', 'string', 'max:120'],
            'authorized_contacts.*.last_name' => ['nullable', 'string', 'max:120'],
            'authorized_contacts.*.document_id' => ['nullable', 'string', 'max:80'],
            'authorized_contacts.*.address' => ['nullable', 'string', 'max:255'],
            'authorized_contacts.*.home_phone' => ['nullable', 'string', 'max:40'],
            'authorized_contacts.*.mobile_phone' => ['nullable', 'string', 'max:40'],
            'authorized_contacts.*.relationship' => ['nullable', 'string', 'max:80'],
            'authorized_contacts.*.work_place' => ['nullable', 'string', 'max:120'],
            'authorized_contacts.*.work_address' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function syncRepresentative(Student $student, array $data): void
    {
        $hasData = collect($data)->filter(fn ($value) => filled($value))->isNotEmpty();
        if (! $hasData) {
            return;
        }

        $representative = $student->representatives()->first() ?: new Representative();
        $representative->fill([
            'campus_id' => $student->campus_id,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'document_id' => $data['document_id'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => ($data['phone'] ?? null) ?: ($data['mobile_phone'] ?? null),
            'home_phone' => $data['home_phone'] ?? null,
            'mobile_phone' => $data['mobile_phone'] ?? null,
            'work_place' => $data['work_place'] ?? null,
            'work_address' => $data['work_address'] ?? null,
            'email' => $data['email'] ?? null,
            'office_phone' => $data['office_phone'] ?? null,
            'relation' => 'Representante',
        ]);
        $representative->save();
        $student->representatives()->syncWithoutDetaching([$representative->id]);
    }

    private function syncAuthorizedContacts(Student $student, array $rows): void
    {
        foreach ([1, 2] as $slot) {
            $row = $rows[$slot - 1] ?? [];
            $hasData = collect($row)->filter(fn ($value) => filled($value))->isNotEmpty();
            $contact = $student->authorizedContacts()->firstOrNew(['slot' => $slot]);

            if (! $hasData) {
                if ($contact->exists) {
                    $contact->delete();
                }
                continue;
            }

            $contact->fill([
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'document_id' => $row['document_id'] ?? null,
                'address' => $row['address'] ?? null,
                'home_phone' => $row['home_phone'] ?? null,
                'mobile_phone' => $row['mobile_phone'] ?? null,
                'relationship' => $row['relationship'] ?? null,
                'work_place' => $row['work_place'] ?? null,
                'work_address' => $row['work_address'] ?? null,
            ]);
            $student->authorizedContacts()->save($contact);
        }
    }
}
