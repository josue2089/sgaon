<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV para abrir directo en Excel en español: BOM UTF-8 (tildes y ñ), separador ";"
 * y montos con coma decimal. #449
 */
final class CsvExport
{
    public const SEPARATOR = ';';

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>|callable(): iterable<int, list<mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable|callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            self::write($out, $headers, $rows);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Escribe el CSV completo en un recurso abierto (descarga o archivo de exportación en cola).
     *
     * @param  resource  $handle
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>|callable(): iterable<int, list<mixed>>  $rows
     */
    public static function write($handle, array $headers, iterable|callable $rows): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, self::SEPARATOR);
        foreach (is_callable($rows) ? $rows() : $rows as $row) {
            self::row($handle, $row);
        }
    }

    /** @param  resource  $handle */
    public static function row($handle, array $row): void
    {
        fputcsv($handle, array_map(fn ($value) => match (true) {
            $value === null => '',
            is_float($value) => self::amount($value),
            default => $value,
        }, $row), self::SEPARATOR);
    }

    public static function amount(float|int|string|null $value, int $decimals = 2): string
    {
        return $value === null || $value === '' ? '' : number_format((float) $value, $decimals, ',', '');
    }
}
