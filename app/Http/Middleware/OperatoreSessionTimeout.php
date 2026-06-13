<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Timeout di inattività 30 minuti per gli operatori agencies
 * (porting del controllo last_activity in AG/api/v1/log.php).
 */
final class OperatoreSessionTimeout
{
    private const LIFETIME = 1800;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('operatore')->check()) {
            $last = (int) $request->session()->get('operatore_last_activity', time());
            if (time() - $last > self::LIFETIME) {
                Auth::guard('operatore')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('login?session_expired=1');
            }
            $request->session()->put('operatore_last_activity', time());
        }

        return $next($request);
    }
}
