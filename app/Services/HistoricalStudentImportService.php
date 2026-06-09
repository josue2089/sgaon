<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Program;
use App\Models\Representative;
use App\Models\Student;
use App\Services\Import\HistoricalImportPreviewResult;
use App\Services\Import\HistoricalImportPreviewRow;
use App\Services\Import\HistoricalImportRunResult;
use App\Support\HistoricalLedgerSpreadsheet;
use App\Support\HistoricalProgramResolver;
use App\Support\HistoricalStudentRowDto;
use App\Support\HistoricalStudentSpreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HistoricalStudentImportService
{
    public function __construct(
        private readonly HistoricalStudentSpreadsheet $wideSpreadsheet = new HistoricalStudentSpreadsheet,
        private readonly HistoricalLedgerSpreadsheet $ledgerSpreadsheet = new HistoricalLedgerSpreadsheet,
        private readonly HistoricalProgramResolver $programResolver = new HistoricalProgramResolver,
    ) {}

    public function detectFormat(string $path): string
    {
        $sheets = (new \App\Support\XlsxReader)->listSheets($path);
        foreach ($sheets as $sheet) {
            $normalized = strtolower($sheet['name']);
            if (str_contains($normalized, 'historico de matricula') && ! str_contains($normalized, 'cclc')) {
                return 'ledger';
            }
        }

        $rows = (new \App\Support\XlsxReader)->readRows($path, 0);
        foreach ($rows as $row) {
            $line = strtolower(implode(' | ', $row));
            if (str_contains($line, 'nombres y apellidos') && str_contains($line, 'documento de identidad')) {
                return 'wide';
            }
            if (str_contains($line, 'fecha') && str_contains($line, 'alumno') && str_contains($line, 'titular')) {
                return 'ledger';
            }
        }

        throw new RuntimeException('No se reconoció el formato del archivo (ancho o ledger 13-16).');
    }

    public function buildPreview(string $path, ?int $defaultCampusId = null, string $filename = ''): HistoricalImportPreviewResult
    {
        $format = $this->detectFormat($path);
        $dtos = $format === 'ledger'
            ? $this->ledgerSpreadsheet->parse($path)
            : $this->wideSpreadsheet->parse($path);

        $campusByCode = $this->campusMap();
        $programNames = Program::query()->pluck('name', 'id');
        $previewRows = [];

        foreach ($dtos as $dto) {
            $previewRows[] = $this->buildPreviewRow($dto, $defaultCampusId, $campusByCode, $programNames);
        }

        return new HistoricalImportPreviewResult(
            filename: $filename !== '' ? $filename : basename($path),
            format: $format,
            defaultCampusId: $defaultCampusId,
            rows: $previewRows,
        );
    }

    public function buildPreviewFromUpload(UploadedFile $file, ?int $defaultCampusId = null): HistoricalImportPreviewResult
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }

        return $this->buildPreview($path, $defaultCampusId, $file->getClientOriginalName());
    }

    public function import(HistoricalImportPreviewResult $preview, bool $dryRun = false): HistoricalImportRunResult
    {
        $result = new HistoricalImportRunResult;
        $validRows = array_filter($preview->rows, fn (HistoricalImportPreviewRow $row) => $row->isValid);

        if ($validRows === []) {
            return $result;
        }

        $run = function () use ($validRows, $dryRun, $result) {
            foreach ($validRows as $row) {
                try {
                    $existing = $this->findExistingStudent($row);
                    $status = $this->resolveImportStatus($row, $existing);

                    $attributes = array_filter([
                        'first_name' => $row->firstName,
                        'last_name' => $row->lastName,
                        'document_id' => $row->documentId !== '' ? $row->documentId : null,
                        'contract_number' => $row->contractNumber !== '' ? $row->contractNumber : null,
                        'birth_date' => $row->birthDate,
                        'email' => $row->email,
                        'phone' => $row->phone,
                        'address' => $row->address,
                        'enrollment_date' => $row->enrollmentDate,
                        'registration_program_id' => $row->registrationProgramId,
                        'salesperson' => $row->salesperson,
                        'promotion' => $row->promotion,
                        'installments' => $row->installments,
                        'status' => $status,
                    ], fn ($value) => $value !== null);

                    if ($dryRun) {
                        $result->updated += $existing ? 1 : 0;
                        $result->created += $existing ? 0 : 1;

                        continue;
                    }

                    if ($existing) {
                        $existing->update($attributes);
                        $student = $existing;
                        $result->updated++;
                    } else {
                        $student = Student::create(array_merge($attributes, [
                            'campus_id' => $row->campusId,
                        ]));
                        $result->created++;
                    }

                    $this->syncRepresentative($student, $row);
                } catch (\Throwable) {
                    $result->failed++;
                }
            }
        };

        if ($dryRun) {
            $run();

            return $result;
        }

        DB::transaction($run);

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function campusMap(): array
    {
        $map = [];
        foreach (Campus::query()->get(['id', 'code']) as $campus) {
            $map[strtoupper((string) $campus->code)] = $campus->id;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $campusByCode
     * @param  \Illuminate\Support\Collection<int, string>  $programNames
     */
    private function buildPreviewRow(
        HistoricalStudentRowDto $dto,
        ?int $defaultCampusId,
        array $campusByCode,
        $programNames,
    ): HistoricalImportPreviewRow {
        $errors = [];
        $warnings = [];
        [$firstName, $lastName] = $this->splitName($dto->fullName);

        if ($lastName === '') {
            $errors[] = 'Apellido es obligatorio.';
        }

        if ($firstName === '') {
            $errors[] = 'Nombre es obligatorio.';
        }

        $campusId = $defaultCampusId;
        if ($dto->campusCode !== null && isset($campusByCode[$dto->campusCode])) {
            $campusId = $campusByCode[$dto->campusCode];
        }

        if ($campusId === null) {
            $errors[] = 'No se pudo determinar la sede (indique sede por defecto o columna SEDE DE ORIGEN).';
        }

        $programId = $this->programResolver->resolve($dto->levelCode);
        $programName = $programId ? ($programNames[$programId] ?? null) : null;
        if ($dto->levelCode !== null && trim($dto->levelCode) !== '' && $programId === null) {
            $warnings[] = "No se pudo mapear el nivel «{$dto->levelCode}» a un programa.";
        }

        if ($dto->enrollmentDate === null) {
            $warnings[] = 'Sin fecha de inscripción; no aparecerá en filtros por año.';
        }

        $forceSheetStatus = $dto->status !== 'active';
        $action = 'create';
        $previewRow = new HistoricalImportPreviewRow(
            lineNumber: $dto->lineNumber,
            sheetName: $dto->sheetName,
            format: $dto->format,
            campusId: (int) $campusId,
            firstName: $firstName,
            lastName: $lastName,
            documentId: $dto->documentId,
            contractNumber: $dto->contractNumber,
            birthDate: $dto->birthDate,
            email: $dto->email,
            phone: $dto->phone,
            address: $dto->address,
            levelCode: $dto->levelCode,
            enrollmentDate: $dto->enrollmentDate,
            status: $dto->status,
            registrationProgramId: $programId,
            programName: $programName,
            salesperson: $dto->salesperson,
            promotion: $dto->promotion,
            installments: $dto->installments,
            representativeName: $dto->representativeName,
            representativeDocumentId: $dto->representativeDocumentId,
            representativeEmail: $dto->representativeEmail,
            representativePhone: $dto->representativePhone,
            action: $action,
            isValid: $errors === [],
            forceSheetStatus: $forceSheetStatus,
            errors: $errors,
            warnings: $warnings,
        );

        if ($errors === []) {
            $existing = $this->findExistingStudent($previewRow);
            if ($existing) {
                $previewRow = new HistoricalImportPreviewRow(
                    lineNumber: $previewRow->lineNumber,
                    sheetName: $previewRow->sheetName,
                    format: $previewRow->format,
                    campusId: $previewRow->campusId,
                    firstName: $previewRow->firstName,
                    lastName: $previewRow->lastName,
                    documentId: $previewRow->documentId,
                    contractNumber: $previewRow->contractNumber,
                    birthDate: $previewRow->birthDate,
                    email: $previewRow->email,
                    phone: $previewRow->phone,
                    address: $previewRow->address,
                    levelCode: $previewRow->levelCode,
                    enrollmentDate: $previewRow->enrollmentDate,
                    status: $previewRow->status,
                    registrationProgramId: $previewRow->registrationProgramId,
                    programName: $previewRow->programName,
                    salesperson: $previewRow->salesperson,
                    promotion: $previewRow->promotion,
                    installments: $previewRow->installments,
                    representativeName: $previewRow->representativeName,
                    representativeDocumentId: $previewRow->representativeDocumentId,
                    representativeEmail: $previewRow->representativeEmail,
                    representativePhone: $previewRow->representativePhone,
                    action: 'update',
                    isValid: true,
                    forceSheetStatus: $forceSheetStatus,
                    errors: [],
                    warnings: $warnings,
                );
            }
        }

        return $previewRow;
    }

    private function findExistingStudent(HistoricalImportPreviewRow $row): ?Student
    {
        $query = Student::query()->where('campus_id', $row->campusId);

        if ($row->documentId !== '') {
            return (clone $query)->where('document_id', $row->documentId)->first();
        }

        if ($row->contractNumber !== '') {
            return (clone $query)->where('contract_number', $row->contractNumber)->first();
        }

        if ($row->enrollmentDate !== null) {
            return (clone $query)
                ->whereRaw('LOWER(first_name) = ?', [strtolower($row->firstName)])
                ->whereRaw('LOWER(last_name) = ?', [strtolower($row->lastName)])
                ->whereDate('enrollment_date', $row->enrollmentDate)
                ->first();
        }

        return (clone $query)
            ->whereRaw('LOWER(first_name) = ?', [strtolower($row->firstName)])
            ->whereRaw('LOWER(last_name) = ?', [strtolower($row->lastName)])
            ->first();
    }

    private function resolveImportStatus(HistoricalImportPreviewRow $row, ?Student $existing): string
    {
        if ($row->forceSheetStatus) {
            return $row->status;
        }

        if ($existing) {
            return $existing->status;
        }

        return $row->status;
    }

    private function syncRepresentative(Student $student, HistoricalImportPreviewRow $row): void
    {
        $repName = trim($row->representativeName);
        if ($repName === '') {
            return;
        }

        [$firstName, $lastName] = $this->splitName($repName);
        $documentId = $row->representativeDocumentId !== '' ? $row->representativeDocumentId : null;

        $rep = Representative::updateOrCreate(
            [
                'campus_id' => $row->campusId,
                'document_id' => $documentId,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ],
            [
                'email' => $row->representativeEmail,
                'phone' => $row->representativePhone,
                'relation' => 'representante',
            ],
        );

        $student->representatives()->syncWithoutDetaching([$rep->id]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) <= 1) {
            return [$name, ''];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }
}
