<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PersonName
{
    /** @return list<string> */
    public static function tokens(?string $name): array
    {
        $clean = strtolower(Str::ascii((string) $name));
        $tokens = preg_split('/[^a-z]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($tokens);

        return $tokens;
    }

    /** ¿Pueden ser la misma persona? "Maria Perez" y "María J. Pérez" sí; "Maria Perez" y "Jose Perez" no. */
    public static function compatible(?string $first, ?string $second): bool
    {
        $a = self::tokens($first);
        $b = self::tokens($second);
        if ($a === [] || $b === []) {
            return true;
        }
        if (array_diff($a, $b) === [] || array_diff($b, $a) === []) {
            return min(count($a), count($b)) >= 2 || $a === $b;
        }

        similar_text(implode(' ', $a), implode(' ', $b), $percent);

        return $percent >= 85;
    }
}
