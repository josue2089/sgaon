<?php

namespace App\Support;

use App\Models\CampusProgramPrice;
use App\Models\Course;

class TuitionPriceResolver
{
    /**
     * Precio de la mensualidad: sede+programa (con su moneda) → nivel → programa (EUR).
     *
     * @return array{amount: float, currency: string, source: string}|null
     */
    public static function forCourse(Course $course, ?int $campusId): ?array
    {
        $programId = $course->program_id ?: $course->programLevel?->program_id;

        if ($campusId && $programId) {
            $campusPrice = CampusProgramPrice::query()
                ->where('campus_id', $campusId)
                ->where('program_id', $programId)
                ->first();

            if ($campusPrice && $campusPrice->amount > 0) {
                return [
                    'amount' => $campusPrice->amount,
                    'currency' => strtoupper($campusPrice->currency),
                    'source' => 'campus_program',
                ];
            }
        }

        $basePriceEur = (float) ($course->programLevel?->resolvedBasePriceEur() ?? 0);
        if ($basePriceEur > 0) {
            return [
                'amount' => $basePriceEur,
                'currency' => PaymentCurrencyConverter::CURRENCY_EUR,
                'source' => 'program_level',
            ];
        }

        return null;
    }
}
