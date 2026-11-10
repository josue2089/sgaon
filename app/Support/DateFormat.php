<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Fechas como las lee el equipo: 25/11/2026. #449 */
final class DateFormat
{
    public static function date(?CarbonInterface $date, string $empty = '—'): string
    {
        return $date ? $date->format('d/m/Y') : $empty;
    }

    public static function dateTime(?CarbonInterface $date, string $empty = '—'): string
    {
        return $date ? $date->format('d/m/Y h:i a') : $empty;
    }
}
