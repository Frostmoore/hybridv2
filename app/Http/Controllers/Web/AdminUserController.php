<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\LegacyHtmlMail;
use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Support\LegacyText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Sezione "Utenti" del pannello admin Hybrid&Go: elenco di TUTTI gli utenti
 * (tutte le agenzie), con attivazione manuale e invio dell'email di reset
 * password (link cambiapassword.php, come il flusso forgot-password v2).
 */
class AdminUserController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $clienti = Cliente::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($w) use ($like) {
                    $w->where('username', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('nome', 'like', $like)
                        ->orWhere('cognome', 'like', $like)
                        ->orWhere('cf', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->appends(['q' => $q]);

        $agenzie = AgenziaNew::pluck('nome_agenzia', 'id');

        return view('admin.users', compact('clienti', 'agenzie', 'q'));
    }

    public function activate(int $id)
    {
        $user = Cliente::find($id);
        if ($user === null) {
            return redirect()->back()->with('status', 'Utente non trovato.');
        }

        $user->active = '1';
        $user->save();

        return redirect()->back()->with('status', "Utente «{$user->username}» attivato.");
    }

    public function sendReset(int $id)
    {
        $user = Cliente::find($id);
        if ($user === null) {
            return redirect()->back()->with('status', 'Utente non trovato.');
        }

        $email = LegacyText::normalizeEmail($user->email);
        if ($email === '') {
            return redirect()->back()->with('status', "L'utente «{$user->username}» non ha un'email valida.");
        }

        // Token fresco: invalida eventuali link precedenti (più sicuro).
        $token = bin2hex(random_bytes(16));
        $user->activation_token = $token;
        $user->save();

        $agency = AgenziaNew::find((int) $user->agenziaid);
        $agencyName = $agency ? (string) $agency->nome_agenzia : 'Hybrid&Go';

        $resetUrl = rtrim((string) config('app.url'), '/')
            .'/cambiapassword.php?a='.urlencode((string) $user->id)
            .'&b='.urlencode($token)
            .'&c='.urlencode((string) $user->agenziaid);

        try {
            Mail::to($email)->send(new LegacyHtmlMail(
                subjectLine: 'Reimposta la tua password su '.$agencyName,
                htmlBody: '<h2>Reimpostazione password</h2>'
                    .'<p>È stata avviata la reimpostazione della password del tuo account. '
                    .'Clicca sul link per impostarne una nuova:</p>'
                    .'<p><a href="'.$resetUrl.'">Reimposta la tua password</a></p>'
                    .'<p>Se non eri tu, ignora questa email.</p>',
                fromName: $agencyName,
            ));
        } catch (\Throwable) {
            return redirect()->back()->with('status', "Invio email non riuscito per «{$user->username}». Riprova.");
        }

        return redirect()->back()->with('status', "Email di reset inviata a {$email}.");
    }
}
