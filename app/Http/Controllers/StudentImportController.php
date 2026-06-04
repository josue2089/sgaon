<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Services\Import\StudentImportPreviewResult;
use App\Services\StudentBulkImportService;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class StudentImportController extends Controller
{
    private const SESSION_KEY = 'student_import_preview';

    public function __construct(
        private readonly StudentBulkImportService $importService,
    ) {}

    private function scopedCampusId(): ?int
    {
        return request()->user()?->isMasterAdmin() ? null : request()->user()?->campus_id;
    }

    public function create(Request $request): View
    {
        $campuses = Campus::query()->where('status', 'active')->orderBy('name');
        if ($this->scopedCampusId()) {
            $campuses->where('id', $this->scopedCampusId());
        }

        return view('students.import', [
            'campuses' => $campuses->get(),
            'defaultCampusId' => $this->scopedCampusId() ?? $request->user()?->campus_id,
            'isMaster' => $request->user()?->isMasterAdmin() ?? false,
            'preview' => null,
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        $campusId = (int) $data['campus_id'];
        $this->authorizeCampus($campusId);

        try {
            $preview = $this->importService->buildPreviewFromUpload($request->file('file'), $campusId);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('students.import')
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        $request->session()->put(self::SESSION_KEY, [
            'filename' => $preview->filename,
            'campus_id' => $preview->campusId,
            'rows' => $preview->rowsToSession(),
        ]);

        $campuses = Campus::query()->where('status', 'active')->orderBy('name');
        if ($this->scopedCampusId()) {
            $campuses->where('id', $this->scopedCampusId());
        }

        return view('students.import', [
            'campuses' => $campuses->get(),
            'defaultCampusId' => $campusId,
            'isMaster' => $request->user()?->isMasterAdmin() ?? false,
            'preview' => $preview,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sessionData = $request->session()->get(self::SESSION_KEY);
        if (! is_array($sessionData) || empty($sessionData['rows'])) {
            return redirect()
                ->route('students.import')
                ->with('error', 'No hay vista previa pendiente. Suba el archivo nuevamente.');
        }

        $campusId = (int) ($sessionData['campus_id'] ?? 0);
        $this->authorizeCampus($campusId);

        $preview = StudentImportPreviewResult::fromSession(
            (string) ($sessionData['filename'] ?? 'import.xlsx'),
            $campusId,
            $sessionData['rows'],
        );

        $result = $this->importService->import($preview);
        $request->session()->forget(self::SESSION_KEY);

        AuditTrail::log($request, 'students.bulk_import', null, [
            'campus_id' => $campusId,
            'created' => $result->created,
            'updated' => $result->updated,
            'skipped' => $result->skipped,
            'failed' => $result->failed,
            'filename' => $preview->filename,
        ]);

        return redirect()
            ->route('students.index')
            ->with('success', sprintf(
                'Importación completada: %d creados, %d actualizados.%s',
                $result->created,
                $result->updated,
                $result->failed > 0 ? " {$result->failed} con error." : '',
            ));
    }

    private function authorizeCampus(int $campusId): void
    {
        $scoped = $this->scopedCampusId();
        if ($scoped !== null && $scoped !== $campusId) {
            abort(403);
        }
    }
}
