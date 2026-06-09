<?php

namespace App\Services\Import;

class HistoricalImportPreviewRow
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $lineNumber,
        public string $sheetName,
        public string $format,
        public int $campusId,
        public string $firstName,
        public string $lastName,
        public string $documentId,
        public string $contractNumber,
        public ?string $birthDate,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $levelCode,
        public ?string $enrollmentDate,
        public string $status,
        public ?int $registrationProgramId,
        public ?string $programName,
        public ?string $salesperson,
        public ?string $promotion,
        public ?int $installments,
        public string $representativeName,
        public string $representativeDocumentId,
        public ?string $representativeEmail,
        public ?string $representativePhone,
        public string $action,
        public bool $isValid,
        public bool $forceSheetStatus,
        public array $errors = [],
        public array $warnings = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'line_number' => $this->lineNumber,
            'sheet_name' => $this->sheetName,
            'format' => $this->format,
            'campus_id' => $this->campusId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'document_id' => $this->documentId,
            'contract_number' => $this->contractNumber,
            'birth_date' => $this->birthDate,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'level_code' => $this->levelCode,
            'enrollment_date' => $this->enrollmentDate,
            'status' => $this->status,
            'registration_program_id' => $this->registrationProgramId,
            'program_name' => $this->programName,
            'salesperson' => $this->salesperson,
            'promotion' => $this->promotion,
            'installments' => $this->installments,
            'representative_name' => $this->representativeName,
            'representative_document_id' => $this->representativeDocumentId,
            'representative_email' => $this->representativeEmail,
            'representative_phone' => $this->representativePhone,
            'action' => $this->action,
            'is_valid' => $this->isValid,
            'force_sheet_status' => $this->forceSheetStatus,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            lineNumber: (int) ($data['line_number'] ?? 0),
            sheetName: (string) ($data['sheet_name'] ?? ''),
            format: (string) ($data['format'] ?? 'wide'),
            campusId: (int) ($data['campus_id'] ?? 0),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            documentId: (string) ($data['document_id'] ?? ''),
            contractNumber: (string) ($data['contract_number'] ?? ''),
            birthDate: isset($data['birth_date']) ? (string) $data['birth_date'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            address: isset($data['address']) ? (string) $data['address'] : null,
            levelCode: isset($data['level_code']) ? (string) $data['level_code'] : null,
            enrollmentDate: isset($data['enrollment_date']) ? (string) $data['enrollment_date'] : null,
            status: (string) ($data['status'] ?? 'inactive'),
            registrationProgramId: isset($data['registration_program_id']) ? (int) $data['registration_program_id'] : null,
            programName: isset($data['program_name']) ? (string) $data['program_name'] : null,
            salesperson: isset($data['salesperson']) ? (string) $data['salesperson'] : null,
            promotion: isset($data['promotion']) ? (string) $data['promotion'] : null,
            installments: isset($data['installments']) ? (int) $data['installments'] : null,
            representativeName: (string) ($data['representative_name'] ?? ''),
            representativeDocumentId: (string) ($data['representative_document_id'] ?? ''),
            representativeEmail: isset($data['representative_email']) ? (string) $data['representative_email'] : null,
            representativePhone: isset($data['representative_phone']) ? (string) $data['representative_phone'] : null,
            action: (string) ($data['action'] ?? 'create'),
            isValid: (bool) ($data['is_valid'] ?? false),
            forceSheetStatus: (bool) ($data['force_sheet_status'] ?? false),
            errors: (array) ($data['errors'] ?? []),
            warnings: (array) ($data['warnings'] ?? []),
        );
    }
}
