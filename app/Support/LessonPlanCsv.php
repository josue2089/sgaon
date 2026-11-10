<?php

namespace App\Support;

/**
 * Lee el CSV del plan de clases de un subnivel: clase; unidad; contenido; tipo. #470
 * Acepta ";" o "," como separador, encabezado opcional, BOM de Excel y el tipo como clave (unit) o como etiqueta (Unidad).
 */
final class LessonPlanCsv
{
    /**
     * @return array{rows: list<array{class_number: int, unit: ?string, content: string, lesson_type: ?string}>, errors: list<string>}
     */
    public static function parse(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        $delimiter = substr_count($lines[0] ?? '', ';') >= substr_count($lines[0] ?? '', ',') ? ';' : ',';
        $types = self::typeLookup();

        $rows = [];
        $errors = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_map('trim', str_getcsv($line, $delimiter));
            $number = $cells[0] ?? '';
            if ($index === 0 && ! ctype_digit($number)) {
                continue; // encabezado
            }

            $lineNumber = $index + 1;
            if (! ctype_digit($number) || (int) $number < 1 || (int) $number > 999) {
                $errors[] = "Fila {$lineNumber}: el número de clase \"{$number}\" no es válido.";

                continue;
            }
            $content = $cells[2] ?? '';
            if ($content === '') {
                $errors[] = "Fila {$lineNumber}: falta el contenido.";

                continue;
            }
            $typeCell = mb_strtolower($cells[3] ?? '');
            $type = $typeCell === '' ? null : ($types[$typeCell] ?? false);
            if ($type === false) {
                $errors[] = "Fila {$lineNumber}: el tipo \"{$cells[3]}\" no existe.";

                continue;
            }

            $rows[(int) $number] = [
                'class_number' => (int) $number,
                'unit' => mb_substr($cells[1] ?? '', 0, 120) ?: null,
                'content' => $content,
                'lesson_type' => $type,
            ];
        }
        ksort($rows);

        return ['rows' => array_values($rows), 'errors' => $errors];
    }

    /** @return array<string, string> */
    private static function typeLookup(): array
    {
        $lookup = [];
        foreach (LessonTemplates::TYPES as $key => $label) {
            $lookup[$key] = $key;
            $lookup[mb_strtolower($label)] = $key;
        }
        $lookup['p.e.'] = 'test';
        $lookup['prueba'] = 'test';

        return $lookup;
    }
}
