<?php

namespace App\Support;

/** Enlace wa.me con el teléfono en formato internacional (Venezuela por defecto). #449 */
final class WhatsappLink
{
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '' || strlen($digits) < 10) {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '58'.substr($digits, 1);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '4')) {
            return '58'.$digits;
        }

        return $digits;
    }

    public static function to(?string $phone, string $text): string
    {
        $number = self::normalizePhone($phone);

        return 'https://wa.me/'.($number ?? '').'?text='.rawurlencode($text);
    }
}
