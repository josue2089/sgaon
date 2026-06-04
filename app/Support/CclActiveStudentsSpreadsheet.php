<?php

namespace App\Support;

use RuntimeException;

class CclActiveStudentsSpreadsheet
{
    public function __construct(
        private readonly XlsxReader $reader = new XlsxReader,
    ) {}

    /**
     * @return list<CclStudentRowDto>
     */
    public function parse(string $path): array
    {
        $rows = $this->reader->readRows($path);
        $headers = [];
        $headerIndex = null;
        $parsed = [];

        foreach ($rows as $index => $row) {
            if ($headers === [] && $this->looksLikeCclHeader($row)) {
                $headers = $this->normalizeHeaders($row);
                $headerIndex = $index;

                continue;
            }

            if ($headers === []) {
                continue;
            }

            $mapped = $this->mapRow($headers, $row);
            $lineNumber = $index + 1;
            $firstName = trim((string) ($mapped['nombre'] ?? ''));
            $lastName = trim((string) ($mapped['apellido'] ?? ''));

            if ($firstName === '' && $lastName === '') {
                continue;
            }

            $parsed[] = new CclStudentRowDto(
                lineNumber: $lineNumber,
                firstName: $firstName,
                middleName: trim((string) ($mapped['segundo_nombre'] ?? '')),
                lastName: $lastName,
                age: $this->parseAge($mapped['edad'] ?? null),
                nivel: trim((string) ($mapped['nivel'] ?? '')),
                statusLabel: trim((string) ($mapped['status'] ?? '')),
            );
        }

        if ($headers === []) {
            throw new RuntimeException(
                'No se encontró la fila de encabezados (se esperan columnas Nombre, Apellido y Nivel).',
            );
        }

        return $parsed;
    }

    private function looksLikeCclHeader(array $row): bool
    {
        $line = strtolower(implode(' | ', $row));

        return str_contains($line, 'nombre')
            && str_contains($line, 'apellido')
            && str_contains($line, 'nivel');
    }

    /**
     * @return array<string, string>
     */
    private function normalizeHeaders(array $row): array
    {
        $headers = [];
        foreach ($row as $i => $header) {
            $key = $this->headerKey(trim((string) $header));
            if ($key === '') {
                $key = 'col_'.$i;
            }
            if (isset($headers[$key])) {
                $key .= '_'.$i;
            }
            $headers[$key] = (string) $i;
        }

        return $headers;
    }

    private function headerKey(string $header): string
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($header)) ?? '';

        return match (true) {
            str_contains($normalized, 'apellido') => 'apellido',
            str_contains($normalized, '2donombre') || str_contains($normalized, 'segundonombre') => 'segundo_nombre',
            $normalized === 'nombre' || (str_starts_with($normalized, 'nombre') && ! str_contains($normalized, 'apellido')) => 'nombre',
            str_starts_with($normalized, 'id') && strlen($normalized) <= 5 && ! str_contains($normalized, 'entidad') => 'id',
            $normalized === 'edad' || str_contains($normalized, 'edad') => 'edad',
            $normalized === 'sexo' => 'sexo',
            $normalized === 'nivel' => 'nivel',
            $normalized === 'horario' => 'horario',
            $normalized === 'status' || $normalized === 'estado' => 'status',
            default => '',
        };
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function mapRow(array $headers, array $row): array
    {
        $out = [];
        foreach ($headers as $key => $index) {
            $out[$key] = $row[(int) $index] ?? null;
        }

        return $out;
    }

    private function parseAge(mixed $value): ?int
    {
        $age = trim((string) $value);
        if ($age === '' || ! is_numeric($age)) {
            return null;
        }

        $int = (int) $age;

        return $int >= 0 && $int <= 120 ? $int : null;
    }
}
