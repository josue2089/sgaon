<?php

namespace App\Services\Import;

class StudentImportPreviewRow
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $lineNumber,
        public string $firstName,
        public string $lastName,
        public string $nivel,
        public string $status,
        public ?string $birthDate,
        public ?int $registrationProgramId,
        public ?string $programName,
        public string $action,
        public bool $isValid,
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
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'nivel' => $this->nivel,
            'status' => $this->status,
            'birth_date' => $this->birthDate,
            'registration_program_id' => $this->registrationProgramId,
            'program_name' => $this->programName,
            'action' => $this->action,
            'is_valid' => $this->isValid,
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
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            nivel: (string) ($data['nivel'] ?? ''),
            status: (string) ($data['status'] ?? 'active'),
            birthDate: isset($data['birth_date']) ? (string) $data['birth_date'] : null,
            registrationProgramId: isset($data['registration_program_id']) ? (int) $data['registration_program_id'] : null,
            programName: isset($data['program_name']) ? (string) $data['program_name'] : null,
            action: (string) ($data['action'] ?? 'create'),
            isValid: (bool) ($data['is_valid'] ?? false),
            errors: (array) ($data['errors'] ?? []),
            warnings: (array) ($data['warnings'] ?? []),
        );
    }
}
