<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helper di normalizzazione ereditati dal _bootstrap.php legacy.
 */
final class LegacyText
{
    /**
     * normalize_email() legacy: trim, taglia tutto dopo '|', lowercase.
     * (Alcune email nel DB legacy hanno formato "email|extra".)
     */
    public static function normalizeEmail(mixed $value): string
    {
        $value = trim(ApiResponse::s($value));
        if ($value !== '' && str_contains($value, '|')) {
            $value = trim(explode('|', $value, 2)[0]);
        }

        return strtolower($value);
    }

    /**
     * Sanifica una stringa come mb_convert_encoding($v, 'UTF-8', 'UTF-8')
     * usato da agency.php legacy (rimuove sequenze UTF-8 non valide).
     */
    public static function utf8(mixed $value): string
    {
        return mb_convert_encoding(ApiResponse::s($value), 'UTF-8', 'UTF-8');
    }
}
