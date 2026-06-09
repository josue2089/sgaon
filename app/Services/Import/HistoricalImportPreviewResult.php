<?php

namespace App\Services\Import;

class HistoricalImportPreviewResult
{
    /**
     * @param  list<HistoricalImportPreviewRow>  $rows
     */
    public function __construct(
        public string $filename,
        public string $format,
        public ?int $defaultCampusId,
        public array $rows,
    ) {}

    public function validCount(): int
    {
        return count(array_filter($this->rows, fn (HistoricalImportPreviewRow $row) => $row->isValid));
    }

    public function errorCount(): int
    {
        return count(array_filter($this->rows, fn (HistoricalImportPreviewRow $row) => ! $row->isValid));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rowsToSession(): array
    {
        return array_map(fn (HistoricalImportPreviewRow $row) => $row->toArray(), $this->rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function fromSession(string $filename, string $format, ?int $defaultCampusId, array $rows): self
    {
        return new self(
            filename: $filename,
            format: $format,
            defaultCampusId: $defaultCampusId,
            rows: array_map(fn (array $row) => HistoricalImportPreviewRow::fromArray($row), $rows),
        );
    }
}
