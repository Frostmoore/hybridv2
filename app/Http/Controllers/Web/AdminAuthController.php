<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login admin del pannello principale (tabella legacy `utenti`).
 * Path legacy: index.html (form) → POST authenticate.php → home.php; logout.php.
 * La registrazione admin NON esiste più (decisione §7.6).
 */
class AdminAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::guard('admin')->check()) {
            return redirect('home');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $nomeutente = (string) $request->input('nomeutente', '');
        $password = (string) $request->input('password', '');

        if ($nomeutente === '' || $password === '') {
            return back()->withErrors('Compila sia nome utente che password!');
        }

        if (! Auth::guard('admin')->attempt(['nomeutente' => $nomeutente, 'password' => $password])) {
            return back()->withErrors('Nome Utente o Password errati!');
        }

        $request->session()->regenerate();

        return redirect('home');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('login');
    }
}
