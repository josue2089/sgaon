<?php

namespace App\Services\Import;

class StudentImportPreviewResult
{
    /**
     * @param  list<StudentImportPreviewRow>  $rows
     */
    public function __construct(
        public string $filename,
        public int $campusId,
        public array $rows,
    ) {}

    public function validCount(): int
    {
        return count(array_filter($this->rows, fn (StudentImportPreviewRow $row) => $row->isValid));
    }

    public function errorCount(): int
    {
        return count($this->rows) - $this->validCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rowsToSession(): array
    {
        return array_map(fn (StudentImportPreviewRow $row) => $row->toArray(), $this->rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function fromSession(string $filename, int $campusId, array $rows): self
    {
        return new self(
            filename: $filename,
            campusId: $campusId,
            rows: array_map(fn (array $row) => StudentImportPreviewRow::fromArray($row), $rows),
        );
    }
}
