<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Replica il contratto di risposta della API v2 legacy
 * (PH/res/api/v2/_bootstrap.php: ok(), err(), s()).
 *
 * I client (app Flutter white-label) si aspettano:
 * - wrapper {"success":true,"data":…} / {"success":false,"error":…,"code":…}
 * - JSON con JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
 * - valori utente serializzati come stringhe ('1'/'0' per i bool, '' per null)
 */
final class ApiResponse
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public static function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return self::json(['success' => true, 'data' => $data], $status);
    }

    public static function err(string $message, string $code = 'ERROR', int $status = 400): JsonResponse
    {
        return self::json(['success' => false, 'error' => $message, 'code' => $code], $status);
    }

    /**
     * Porting 1:1 dell'helper legacy s(): ogni valore diventa stringa.
     */
    public static function s(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Applica s() a ogni valore di una mappa, preservando le chiavi.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    public static function stringify(array $row): array
    {
        return array_map(self::s(...), $row);
    }

    private static function json(array $payload, int $status): JsonResponse
    {
        return new JsonResponse(
            $payload,
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
            self::JSON_FLAGS,
        );
    }
}
