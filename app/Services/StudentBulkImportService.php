<?php

namespace App\Services;

use App\Models\Student;
use App\Services\Import\StudentImportPreviewResult;
use App\Services\Import\StudentImportPreviewRow;
use App\Services\Import\StudentImportRunResult;
use App\Support\CclActiveStudentsSpreadsheet;
use App\Support\CclStudentRowDto;
use App\Support\StudentRegistrationProgramResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StudentBulkImportService
{
    public function __construct(
        private readonly CclActiveStudentsSpreadsheet $spreadsheet = new CclActiveStudentsSpreadsheet,
        private readonly StudentRegistrationProgramResolver $programResolver = new StudentRegistrationProgramResolver,
    ) {}

    public function buildPreview(string $path, int $campusId, string $filename = ''): StudentImportPreviewResult
    {
        $rows = $this->spreadsheet->parse($path);
        $nameCounts = $this->countDuplicateNames($rows);
        $previewRows = [];

        foreach ($rows as $dto) {
            $previewRows[] = $this->buildPreviewRow($dto, $campusId, $nameCounts);
        }

        return new StudentImportPreviewResult(
            filename: $filename !== '' ? $filename : basename($path),
            campusId: $campusId,
            rows: $previewRows,
        );
    }

    public function buildPreviewFromUpload(UploadedFile $file, int $campusId): StudentImportPreviewResult
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }

        return $this->buildPreview($path, $campusId, $file->getClientOriginalName());
    }

    public function import(StudentImportPreviewResult $preview, bool $dryRun = false): StudentImportRunResult
    {
        $result = new StudentImportRunResult;
        $validRows = array_filter($preview->rows, fn (StudentImportPreviewRow $row) => $row->isValid);

        if ($validRows === []) {
            return $result;
        }

        $run = function () use ($validRows, $preview, $dryRun, $result) {
            foreach ($validRows as $row) {
                try {
                    $existing = $this->findExistingStudent($preview->campusId, $row);
                    $attributes = [
                        'first_name' => $row->firstName,
                        'last_name' => $row->lastName,
                        'status' => $row->status,
                        'birth_date' => $row->birthDate,
                        'registration_program_id' => $row->registrationProgramId,
                    ];

                    if ($dryRun) {
                        $result->updated += $existing ? 1 : 0;
                        $result->created += $existing ? 0 : 1;

                        continue;
                    }

                    if ($existing) {
                        $existing->update($attributes);
                        $result->updated++;
                    } else {
                        Student::create(array_merge($attributes, [
                            'campus_id' => $preview->campusId,
                        ]));
                        $result->created++;
                    }
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
     * @param  list<CclStudentRowDto>  $rows
     * @return array<string, int>
     */
    private function countDuplicateNames(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $key = $this->nameKey($row->fullFirstName(), $row->lastName);
            if ($key === '') {
                continue;
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $nameCounts
     */
    private function buildPreviewRow(CclStudentRowDto $dto, int $campusId, array $nameCounts): StudentImportPreviewRow
    {
        $errors = [];
        $warnings = [];
        $firstName = $dto->fullFirstName();
        $lastName = $dto->lastName;
        $nivel = trim($dto->nivel);

        if ($lastName === '') {
            $errors[] = 'Apellido es obligatorio.';
        }

        if ($firstName === '') {
            $errors[] = 'Nombre es obligatorio.';
        }

        if ($nivel === '') {
            $errors[] = 'Nivel es obligatorio.';
        }

        $program = $nivel !== '' ? $this->programResolver->resolve($nivel) : null;
        if ($nivel !== '' && ! $program) {
            $message = $this->programResolver->resolveMessage($nivel);
            $errors[] = $message !== '' ? $message : "No se pudo mapear el nivel «{$nivel}».";
        }

        $nameKey = $this->nameKey($firstName, $lastName);
        if ($nameKey !== '' && ($nameCounts[$nameKey] ?? 0) > 1) {
            $warnings[] = "El alumno «{$firstName} {$lastName}» aparece más de una vez en el archivo.";
        }

        $status = $this->mapStatus($dto->statusLabel);
        $birthDate = $dto->age !== null ? $this->birthDateFromAge($dto->age) : null;

        $action = 'create';
        if ($errors === []) {
            $existing = $this->findExistingStudent($campusId, new StudentImportPreviewRow(
                lineNumber: $dto->lineNumber,
                firstName: $firstName,
                lastName: $lastName,
                nivel: $nivel,
                status: $status,
                birthDate: $birthDate,
                registrationProgramId: $program?->id,
                programName: $program?->name,
                action: 'create',
                isValid: true,
            ));
            if ($existing) {
                $action = 'update';
            }
        }

        return new StudentImportPreviewRow(
            lineNumber: $dto->lineNumber,
            firstName: $firstName,
            lastName: $lastName,
            nivel: $nivel,
            status: $status,
            birthDate: $birthDate,
            registrationProgramId: $program?->id,
            programName: $program?->name,
            action: $action,
            isValid: $errors === [],
            errors: $errors,
            warnings: $warnings,
        );
    }

    private function findExistingStudent(int $campusId, StudentImportPreviewRow $row): ?Student
    {
        return Student::query()
            ->where('campus_id', $campusId)
            ->whereRaw('LOWER(first_name) = ?', [strtolower($row->firstName)])
            ->whereRaw('LOWER(last_name) = ?', [strtolower($row->lastName)])
            ->first();
    }

    private function nameKey(string $firstName, string $lastName): string
    {
        $first = strtolower(trim($firstName));
        $last = strtolower(trim($lastName));

        if ($first === '' && $last === '') {
            return '';
        }

        return $first.'|'.$last;
    }

    private function mapStatus(string $label): string
    {
        $normalized = strtolower(trim($label));

        if (in_array($normalized, ['inactivo', 'inactive', 'retirado', 'egresado'], true)) {
            return 'inactive';
        }

        return 'active';
    }

    private function birthDateFromAge(int $age): string
    {
        $year = (int) date('Y') - $age;

        return sprintf('%d-07-01', $year);
    }
}
