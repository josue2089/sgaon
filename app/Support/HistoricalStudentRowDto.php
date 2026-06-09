<?php

namespace App\Support;

readonly class HistoricalStudentRowDto
{
    public function __construct(
        public int $lineNumber,
        public string $sheetName,
        public string $format,
        public ?string $campusCode,
        public string $fullName,
        public string $documentId,
        public string $contractNumber,
        public ?string $birthDate,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $levelCode,
        public ?string $enrollmentDate,
        public string $status,
        public ?string $salesperson,
        public ?string $promotion,
        public ?int $installments,
        public string $representativeName,
        public string $representativeDocumentId,
        public ?string $representativeEmail,
        public ?string $representativePhone,
    ) {}
}
