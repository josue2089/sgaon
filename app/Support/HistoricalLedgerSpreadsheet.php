<?php

namespace App\Support;

use Illuminate\Support\Str;

class HistoricalLedgerSpreadsheet
{
    public function __construct(
        private readonly XlsxReader $reader = new XlsxReader,
        private readonly ExcelDateParser $dateParser = new ExcelDateParser,
    ) {}

    /**
     * @return list<HistoricalStudentRowDto>
     */
    public function parse(string $path, string $defaultStatus = 'inactive'): array
    {
        $rows = $this->reader->readRows($path, 0);
        $headerIndex = null;

        foreach ($rows as $index => $row) {
            if ($this->looksLikeHeader($row)) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            return [];
        }

        $headers = array_map(fn (string $h) => $this->normalizeLabel($h), $rows[$headerIndex]);
        $parsed = [];

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $mapped = $this->mapRow($headers, $rows[$i]);
            $fullName = trim((string) ($mapped['alumno'] ?? ''));
            if ($fullName === '') {
                continue;
            }

            $parsed[] = new HistoricalStudentRowDto(
                lineNumber: $i + 1,
                sheetName: 'HISTORICO DE MATRICULA',
                format: 'ledger',
                campusCode: 'PICACHO',
                fullName: $fullName,
                documentId: $this->cleanDocument($mapped['documento_de_identidad'] ?? null),
                contractNumber: trim((string) ($mapped['n_de_contrato'] ?? $mapped['no_de_contrato'] ?? '')),
                birthDate: null,
                email: null,
                phone: $this->cleanPhone($mapped['telefono'] ?? null),
                address: null,
                levelCode: null,
                enrollmentDate: $this->dateParser->parse($mapped['fecha'] ?? null),
                status: $defaultStatus,
                salesperson: null,
                promotion: null,
                installments: $this->parseInstallments($mapped['n_cuotas'] ?? $mapped['no_cuotas'] ?? null),
                representativeName: trim((string) ($mapped['titular'] ?? '')),
                representativeDocumentId: '',
                representativeEmail: null,
                representativePhone: null,
            );
        }

        return $parsed;
    }

    private function looksLikeHeader(array $row): bool
    {
        $line = Str::lower(Str::ascii(implode(' | ', $row)));

        return str_contains($line, 'fecha') && str_contains($line, 'documento de identidad') && str_contains($line, 'alumno');
    }

    private function normalizeLabel(string $label): string
    {
        $ascii = Str::ascii(trim($label));
        $ascii = preg_replace('/\s+/', ' ', $ascii) ?? $ascii;
        $ascii = str_replace(['N°', 'Nº', 'N '], 'n ', $ascii);
        $ascii = Str::lower($ascii);

        return str_replace([' ', '.', '/'], '_', $ascii);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $headers, array $row): array
    {
        $out = [];
        foreach ($headers as $i => $header) {
            if ($header === '') {
                continue;
            }
            $key = isset($out[$header]) ? $header.'_'.$i : $header;
            $out[$key] = $row[$i] ?? null;
        }

        return $out;
    }

    private function cleanDocument(mixed $value): string
    {
        $doc = trim((string) $value);
        if ($doc === '' || $doc === '0') {
            return '';
        }

        return $doc;
    }

    private function cleanPhone(mixed $value): ?string
    {
        $phone = trim((string) $value);

        return $phone !== '' ? $phone : null;
    }

    private function parseInstallments(mixed $value): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }
}
