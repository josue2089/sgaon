<?php

namespace App\Support;

use Carbon\Carbon;

class ExcelDateParser
{
    public function parse(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '' || in_array(strtolower($raw), ['0', 'n/a', 'na', '-'], true)) {
            return null;
        }

        $raw = preg_replace('/\s+REN$/i', '', $raw) ?? $raw;
        $raw = trim($raw);

        if (is_numeric($raw)) {
            $serial = (float) $raw;
            if ($serial >= 20000 && $serial <= 80000) {
                return Carbon::create(1899, 12, 30)->addDays((int) $serial)->format('Y-m-d');
            }
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);
                if ($date && $date->year >= 1990 && $date->year <= 2100) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $raw, $matches)) {
            $year = (int) $matches[3];
            if ($year < 100) {
                $year += 2000;
            }
            if ($year >= 1990 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, (int) $matches[2], (int) $matches[1]);
            }
        }

        return null;
    }
}
