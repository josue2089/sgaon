<?php

namespace App\Support;

/**
 * Bandas CEFR con los rangos GSE oficiales de Pearson. #469
 * El cliente usa etiquetas compuestas ("A1+ / A2 Kids", "B2+ / C1"): el rango sugerido va del
 * mínimo de la primera banda al máximo de la última. A1+ no es una banda GSE propia: se toma A1.
 */
final class CefrScale
{
    /** @var array<string, array{0: int, 1: int}> */
    public const GSE_BANDS = [
        'A1' => [22, 29],
        'A2' => [30, 35],
        'A2+' => [36, 42],
        'B1' => [43, 50],
        'B1+' => [51, 58],
        'B2' => [59, 66],
        'B2+' => [67, 75],
        'C1' => [76, 84],
        'C2' => [85, 90],
    ];

    public const AUDIENCES = [
        'kids' => 'Niños',
        'teens' => 'Adolescentes',
        'adults' => 'Adultos',
    ];

    /** @return array{0: int, 1: int}|null */
    public static function gseRange(?string $cefrLabel): ?array
    {
        if (! $cefrLabel || ! preg_match_all('/\b([ABC][12])(\+?)/i', $cefrLabel, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $bands = [];
        foreach ($matches as $match) {
            $code = strtoupper($match[1]).$match[2];
            $bands[] = self::GSE_BANDS[$code] ?? self::GSE_BANDS[strtoupper($match[1])] ?? null;
        }
        $bands = array_filter($bands);
        if ($bands === []) {
            return null;
        }

        return [min(array_column($bands, 0)), max(array_column($bands, 1))];
    }

    public static function summary(?string $cefrLabel, ?int $gseMin, ?int $gseMax): ?string
    {
        if (! $cefrLabel) {
            return null;
        }
        $gse = $gseMin && $gseMax ? " · GSE {$gseMin}–{$gseMax}" : '';

        return 'CEFR '.$cefrLabel.$gse;
    }
}
