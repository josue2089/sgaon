<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Program;
use App\Models\Student;
use App\Services\HistoricalStudentImportService;
use App\Services\Import\HistoricalImportPreviewResult;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class HistoricalStudentController extends Controller
{
    private const SESSION_KEY = 'historical_student_import_preview';

    /** @var list<string> */
    private const HISTORICAL_STATUSES = ['inactive', 'graduated', 'withdrawn'];

    public function __construct(
        private readonly HistoricalStudentImportService $importService,
    ) {}

    private function scopedCampusId(): ?int
    {
        return \App\Support\CampusScope::campusIdFor(request()->user());
    }

    public function index(Request $request): View
    {
        $filters = $this->listFilters($request);
        $query = $this->filteredQuery($request)
            ->with(['campus', 'registrationProgram']);

        $baseStatsQuery = Student::query()->whereIn('status', self::HISTORICAL_STATUSES);
        if ($this->scopedCampusId()) {
            $baseStatsQuery->where('campus_id', $this->scopedCampusId());
        }

        $campuses = Campus::query()->where('status', 'active')->orderBy('name');
        if ($this->scopedCampusId()) {
            $campuses->where('id', $this->scopedCampusId());
        }

        $years = Student::query()
            ->whereIn('status', self::HISTORICAL_STATUSES)
            ->when($this->scopedCampusId(), fn (Builder $q) => $q->where('campus_id', $this->scopedCampusId()))
            ->whereNotNull('enrollment_date')
            ->pluck('enrollment_date')
            ->map(fn ($date) => (int) \Carbon\Carbon::parse($date)->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        return view('students.historical.index', [
            'students' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'campuses' => $campuses->get(),
            'programs' => Program::query()->orderBy('name')->get(['id', 'name']),
            'years' => $years,
            'isMaster' => $request->user()?->isMasterAdmin() ?? false,
            'summary' => [
                'total' => (clone $baseStatsQuery)->count(),
                'inactive' => (clone $baseStatsQuery)->where('status', 'inactive')->count(),
                'graduated' => (clone $baseStatsQuery)->where('status', 'graduated')->count(),
                'withdrawn' => (clone $baseStatsQuery)->where('status', 'withdrawn')->count(),
            ],
        ]);
    }

    public function importForm(Request $request): View
    {
        $campuses = Campus::query()->where('status', 'active')->orderBy('name');
        if ($this->scopedCampusId()) {
            $campuses->where('id', $this->scopedCampusId());
        }

        return view('students.historical.import', [
            'campuses' => $campuses->get(),
            'defaultCampusId' => $this->scopedCampusId() ?? $request->user()?->campus_id,
            'isMaster' => $request->user()?->isMasterAdmin() ?? false,
            'preview' => null,
        ]);
    }

    public function importPreview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $campusId = isset($data['campus_id']) ? (int) $data['campus_id'] : null;
        if ($campusId !== null) {
            $this->authorizeCampus($campusId);
        }

        try {
            $preview = $this->importService->buildPreviewFromUpload($request->file('file'), $campusId);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('students.historical.import')
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        $request->session()->put(self::SESSION_KEY, [
            'filename' => $preview->filename,
            'format' => $preview->format,
            'default_campus_id' => $preview->defaultCampusId,
            'rows' => $preview->rowsToSession(),
        ]);

        $campuses = Campus::query()->where('status', 'active')->orderBy('name');
        if ($this->scopedCampusId()) {
            $campuses->where('id', $this->scopedCampusId());
        }

        return view('students.historical.import', [
            'campuses' => $campuses->get(),
            'defaultCampusId' => $campusId ?? $this->scopedCampusId() ?? $request->user()?->campus_id,
            'isMaster' => $request->user()?->isMasterAdmin() ?? false,
            'preview' => $preview,
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $sessionData = $request->session()->get(self::SESSION_KEY);
        if (! is_array($sessionData) || empty($sessionData['rows'])) {
            return redirect()
                ->route('students.historical.import')
                ->with('error', 'No hay vista previa pendiente. Suba el archivo nuevamente.');
        }

        $preview = HistoricalImportPreviewResult::fromSession(
            (string) ($sessionData['filename'] ?? 'import.xlsx'),
            (string) ($sessionData['format'] ?? 'wide'),
            isset($sessionData['default_campus_id']) ? (int) $sessionData['default_campus_id'] : null,
            $sessionData['rows'],
        );

        $result = $this->importService->import($preview);
        $request->session()->forget(self::SESSION_KEY);

        AuditTrail::log($request, 'students.historical.import', null, [
            'filename' => $preview->filename,
            'format' => $preview->format,
            'created' => $result->created,
            'updated' => $result->updated,
            'failed' => $result->failed,
        ]);

        return redirect()
            ->route('students.historical.index')
            ->with('success', sprintf(
                'Importación histórica completada: %d creados, %d actualizados.%s',
                $result->created,
                $result->updated,
                $result->failed > 0 ? " {$result->failed} con error." : '',
            ));
    }

    public function activate(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeHistoricalStudent($student);

        $previousStatus = $student->status;
        $student->update(['status' => 'active']);

        AuditTrail::log($request, 'students.historical.activate', $student, [
            'previous_status' => $previousStatus,
        ]);

        return redirect()
            ->route('students.historical.index', $request->only(['year', 'campus_id', 'status', 'q', 'registration_program_id']))
            ->with('success', "Alumno {$student->full_name} activado correctamente.");
    }

    private function authorizeHistoricalStudent(Student $student): void
    {
        if (! in_array($student->status, self::HISTORICAL_STATUSES, true)) {
            abort(404);
        }

        $scoped = $this->scopedCampusId();
        if ($scoped !== null && $student->campus_id !== $scoped) {
            abort(403);
        }
    }

    /**
     * @return array{q: string, year: string, campus_id: string, status: string, registration_program_id: string}
     */
    private function listFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'year' => (string) $request->query('year', ''),
            'campus_id' => (string) $request->query('campus_id', ''),
            'status' => (string) $request->query('status', ''),
            'registration_program_id' => (string) $request->query('registration_program_id', ''),
        ];
    }

    private function filteredQuery(Request $request): Builder
    {
        $filters = $this->listFilters($request);

        $query = Student::query()
            ->whereIn('status', self::HISTORICAL_STATUSES)
            ->latest('enrollment_date');

        $scoped = $this->scopedCampusId();
        if ($scoped) {
            $query->where('campus_id', $scoped);
        } elseif ($filters['campus_id'] !== '') {
            $query->where('campus_id', (int) $filters['campus_id']);
        }

        if ($filters['year'] !== '') {
            $query->whereYear('enrollment_date', (int) $filters['year']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['registration_program_id'] !== '') {
            $query->where('registration_program_id', (int) $filters['registration_program_id']);
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"])
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('document_id', 'like', "%{$q}%")
                    ->orWhere('contract_number', 'like', "%{$q}%");
            });
        }

        return $query;
    }

    private function authorizeCampus(int $campusId): void
    {
        $scoped = $this->scopedCampusId();
        if ($scoped !== null && $scoped !== $campusId) {
            abort(403);
        }
    }
}
