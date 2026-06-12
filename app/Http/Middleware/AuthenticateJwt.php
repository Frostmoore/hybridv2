<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\JwtService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replica require_auth() della v2 legacy: Bearer JWT obbligatorio,
 * 401 UNAUTHORIZED con lo stesso messaggio in caso di token assente/invalido/scaduto.
 *
 * I claims decodificati sono disponibili come $request->attributes->get('jwt');
 */
final class AuthenticateJwt
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            $claims = $this->jwt->decode(substr($header, 7));
            if ($claims !== null) {
                $request->attributes->set('jwt', $claims);

                return $next($request);
            }
        }

        return ApiResponse::err('Token non valido o scaduto.', 'UNAUTHORIZED', 401);
    }
}
