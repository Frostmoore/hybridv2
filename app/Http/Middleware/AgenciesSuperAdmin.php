<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limita le route di gestione operatori del pannello agenzie ai soli super-admin
 * (config `hybrid.agencies_superadmins`). Va applicato DOPO `auth:operatore`.
 */
class AgenciesSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $operatore = Auth::guard('operatore')->user();
        $isSuper = $operatore !== null
            && in_array($operatore->username, (array) config('hybrid.agencies_superadmins', []), true);

        if (! $isSuper) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Non autorizzato.'], 403);
            }

            return redirect('home');
        }

        return $next($request);
    }
}
