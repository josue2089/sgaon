<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

class HistoricalStudentSpreadsheet
{
    private const SKIP_SHEETS = ['convenio-becas', 'traspasos'];

    public function __construct(
        private readonly XlsxReader $reader = new XlsxReader,
        private readonly ExcelDateParser $dateParser = new ExcelDateParser,
    ) {}

    /**
     * @return list<HistoricalStudentRowDto>
     */
    public function parse(string $path): array
    {
        $rows = [];
        foreach ($this->reader->readAllSheets($path) as $sheet) {
            $status = $this->statusForSheet($sheet['name']);
            if ($status === null) {
                continue;
            }

            $sheetRows = $this->parseSheet($sheet['rows'], $sheet['name'], $status);
            array_push($rows, ...$sheetRows);
        }

        return $rows;
    }

    public function statusForSheet(string $sheetName): ?string
    {
        $normalized = Str::lower(Str::ascii(trim($sheetName)));

        foreach (self::SKIP_SHEETS as $skip) {
            if (str_contains($normalized, $skip)) {
                return null;
            }
        }

        if (str_contains($normalized, 'egresad') || str_contains($normalized, 'historico matriculas cclc')) {
            return 'graduated';
        }

        if (str_contains($normalized, 'matriculas') || str_contains($normalized, 'matricula')) {
            return 'active';
        }

        return null;
    }

    /**
     * @param  list<list<string>>  $sheetRows
     * @return list<HistoricalStudentRowDto>
     */
    private function parseSheet(array $sheetRows, string $sheetName, string $status): array
    {
        $headerIndex = null;
        foreach ($sheetRows as $index => $row) {
            if ($this->looksLikeHeader($row)) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            return [];
        }

        $headers = $this->normalizeHeaders($sheetRows[$headerIndex]);
        $parsed = [];

        for ($i = $headerIndex + 1; $i < count($sheetRows); $i++) {
            $mapped = $this->mapRow($headers, $sheetRows[$i]);
            $fullName = trim((string) ($mapped['nombres_y_apellidos'] ?? ''));
            if ($fullName === '') {
                continue;
            }

            $documentId = $this->cleanDocument($mapped['documento_de_identidad'] ?? null);
            $contractNumber = trim((string) ($mapped['n_expediente'] ?? $mapped['no_expediente'] ?? $mapped['n_de_contrato'] ?? $mapped['no_de_contrato'] ?? ''));
            [$firstRep, $lastRep] = $this->splitName(trim((string) ($mapped['nombre_y_apellido'] ?? '')));

            $parsed[] = new HistoricalStudentRowDto(
                lineNumber: $i + 1,
                sheetName: $sheetName,
                format: 'wide',
                campusCode: $this->normalizeCampusCode($mapped['sede_de_origen'] ?? null),
                fullName: $fullName,
                documentId: $documentId,
                contractNumber: $contractNumber,
                birthDate: $this->dateParser->parse($mapped['fecha_de_nacimiento'] ?? null),
                email: $this->cleanEmail($mapped['correo'] ?? null),
                phone: $this->cleanPhone($mapped['telefono'] ?? null),
                address: $this->nullableString($mapped['direccion'] ?? null),
                levelCode: $this->resolveLevelCode($mapped),
                enrollmentDate: $this->resolveEnrollmentDate($mapped),
                status: $status,
                salesperson: $this->nullableString($mapped['comercial'] ?? null),
                promotion: $this->nullableString($mapped['promocion'] ?? null),
                installments: null,
                representativeName: trim($firstRep.' '.$lastRep),
                representativeDocumentId: $this->cleanDocument($mapped['documento_de_identidad2'] ?? null),
                representativeEmail: $this->cleanEmail($mapped['correo2'] ?? null),
                representativePhone: $this->cleanPhone($mapped['telefono_rep'] ?? $mapped['telefono_1'] ?? null),
            );
        }

        return $parsed;
    }

    private function looksLikeHeader(array $row): bool
    {
        $line = Str::lower(Str::ascii(implode(' | ', $row)));

        return str_contains($line, 'nombres y apellidos') && str_contains($line, 'documento de identidad');
    }

    /**
     * @param  list<string>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn (string $header) => $this->normalizeLabel($header), $headers);
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

            $key = $header;
            if (isset($out[$key])) {
                if (str_contains($key, 'fecha_de_inscripcion')) {
                    $key = 'fecha_de_inscripcion_'.$i;
                } elseif (str_contains($key, 'nivel')) {
                    $key = 'nivel_'.$i;
                } else {
                    $key .= '_'.$i;
                }
            }

            if ($key === 'telefono' && isset($out['telefono'])) {
                $key = 'telefono_rep';
            }

            $out[$key] = $row[$i] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveLevelCode(array $mapped): ?string
    {
        foreach ($mapped as $key => $value) {
            if (! str_contains((string) $key, 'nivel')) {
                continue;
            }

            $code = trim((string) $value);
            if ($code !== '') {
                return $code;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveEnrollmentDate(array $mapped): ?string
    {
        $dates = [];
        foreach ($mapped as $key => $value) {
            if (! str_contains((string) $key, 'fecha_de_inscripcion')) {
                continue;
            }

            $parsed = $this->dateParser->parse($value);
            if ($parsed !== null) {
                $dates[] = $parsed;
            }
        }

        if ($dates === []) {
            return null;
        }

        sort($dates);

        return $dates[0];
    }

    private function cleanDocument(mixed $value): string
    {
        $doc = trim((string) $value);
        if ($doc === '' || $doc === '0') {
            return '';
        }

        return $doc;
    }

    private function cleanEmail(mixed $value): ?string
    {
        $email = trim((string) $value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
    }

    private function cleanPhone(mixed $value): ?string
    {
        $phone = trim((string) $value);

        return $phone !== '' ? $phone : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function normalizeCampusCode(mixed $value): ?string
    {
        $code = Str::upper(Str::ascii(trim((string) $value)));
        if ($code === '') {
            return null;
        }

        if (str_contains($code, 'CASCADA') || str_contains($code, 'CCLC')) {
            return 'CASCADA';
        }

        if (str_contains($code, 'PICACHO')) {
            return 'PICACHO';
        }

        return $code;
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
