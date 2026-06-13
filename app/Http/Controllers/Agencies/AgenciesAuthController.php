<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agencies;

use App\Http\Controllers\Controller;
use App\Models\Operatore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Auth pannello agencies: login.php (pagina) + AJAX api/v1/log.php
 * + api/v1/logout.php. Contratto JSON identico al legacy.
 */
class AgenciesAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::guard('operatore')->check()) {
            return redirect('home');
        }

        return view('agencies.login', [
            'sessionExpired' => $request->query->has('session_expired'),
        ]);
    }

    // ─── POST api/v1/log.php ─────────────────────────────────────────────

    public function apiLogin(Request $request)
    {
        $username = trim((string) $request->input('username', ''));
        $password = trim((string) $request->input('password', ''));

        if ($username === '' || $password === '') {
            return response()->json(['success' => false, 'message' => 'Richiesta non valida.']);
        }

        // Lookup per username O email, come il legacy
        $operatore = Operatore::where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if ($operatore === null || ! Hash::check($password, (string) $operatore->password)) {
            return response()->json(['success' => false, 'message' => 'Credenziali non valide.']);
        }

        if ((string) $operatore->active !== '1') {
            return response()->json(['success' => false, 'message' => 'Account disattivato.']);
        }

        $operatore->last_login = now()->format('Y-m-d H:i:s');
        $operatore->save();

        Auth::guard('operatore')->login($operatore);
        $request->session()->regenerate();
        $request->session()->put('operatore_last_activity', time());

        return response()->json(['success' => true, 'message' => 'Login riuscito']);
    }

    // ─── GET api/v1/logout.php ───────────────────────────────────────────

    public function logout(Request $request)
    {
        Auth::guard('operatore')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('login');
    }
}
