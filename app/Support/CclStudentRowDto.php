<?php

namespace App\Support;

readonly class CclStudentRowDto
{
    public function __construct(
        public int $lineNumber,
        public string $firstName,
        public string $middleName,
        public string $lastName,
        public ?int $age,
        public string $nivel,
        public string $statusLabel,
    ) {}

    public function fullFirstName(): string
    {
        $middle = trim($this->middleName);

        return trim($this->firstName.($middle !== '' ? ' '.$middle : ''));
    }
}
