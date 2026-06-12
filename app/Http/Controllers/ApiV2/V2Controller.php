<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Base per gli endpoint v2: input/claims con la stessa semantica del legacy.
 */
abstract class V2Controller extends Controller
{
    /**
     * json_body() legacy: php://input decodificato, [] se non valido.
     *
     * @return array<string, mixed>
     */
    protected function jsonBody(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Claims del JWT validato dal middleware auth.jwt.
     *
     * @return array<string, mixed>
     */
    protected function claims(Request $request): array
    {
        return (array) $request->attributes->get('jwt', []);
    }
}
