<?php

namespace App\Support;

final class DocumentNumber
{
    /** Cédula sin prefijo V-/E-, puntos ni espacios: "V-12.345.678" → "12345678". Null si queda vacía. */
    public static function normalize(?string $document): ?string
    {
        $clean = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) $document));
        $clean = (string) preg_replace('/^[VEJGP](?=\d)/', '', $clean);

        return $clean === '' ? null : $clean;
    }
}
