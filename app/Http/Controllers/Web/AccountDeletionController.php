<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\LegacyHtmlMail;
use App\Models\Cliente;
use App\Models\DeleteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Flusso cancellazione account (richiesto dagli store):
 * /delete_account.php e /disable_account.php (il legacy aveva due copie
 * quasi identiche: qui sono la stessa pagina) → email con link 60 minuti →
 * /remove_account.php?token= → conferma con credenziali → DELETE.
 */
class AccountDeletionController extends Controller
{
    // ─── GET delete_account.php | disable_account.php ────────────────────

    public function showRequestForm(Request $request)
    {
        return view('public.delete_account', ['action' => $request->path()]);
    }

    // ─── POST delete_account.php | disable_account.php ───────────────────

    public function submitRequest(Request $request)
    {
        $email = filter_var((string) $request->input('email', ''), FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            return $this->esito($request, 'Attenzione', 'Inserisci un indirizzo email valido.');
        }

        $exists = Cliente::where('email', $email)->exists();
        if (! $exists) {
            return $this->esito($request, 'Attenzione', 'Nessun account associato a questa email.');
        }

        $token = bin2hex(random_bytes(16));
        DeleteRequest::create([
            'email' => $email,
            'token' => $token,
            'expiration' => now()->addHour(),
        ]);

        $link = rtrim((string) config('app.url'), '/').'/remove_account.php?token='.$token;

        try {
            Mail::to($email)->send(new LegacyHtmlMail(
                subjectLine: 'Conferma richiesta cancellazione account',
                htmlBody: '<p>Clicca sul seguente link per confermare la cancellazione del tuo account (valido per 60 minuti):</p>'
                    .'<p><a href="'.$link.'">'.$link.'</a></p>',
            ));
        } catch (\Throwable) {
            return $this->esito($request, 'Attenzione', "Errore durante l'invio dell'email. Riprova.");
        }

        return $this->esito(
            $request,
            'Richiesta inviata con successo',
            "Ti è stata inviata un'email con le istruzioni per rimuovere il tuo account. Il link che dovrai seguire sarà valido per 60 minuti, assicurati di controllare nella posta indesiderata",
        );
    }

    // ─── GET remove_account.php?token= ───────────────────────────────────

    public function showConfirmForm(Request $request)
    {
        $token = (string) $request->query('token', '');
        $invalid = $this->validateToken($token);
        if ($invalid !== null) {
            return view('public.esito', ['title' => 'Attenzione', 'text' => $invalid]);
        }

        return view('public.remove_account', ['token' => $token]);
    }

    // ─── POST remove_account.php ─────────────────────────────────────────

    public function confirmDeletion(Request $request)
    {
        $token = (string) $request->input('token', '');
        $invalid = $this->validateToken($token);
        if ($invalid !== null) {
            return view('public.esito', ['title' => 'Attenzione', 'text' => $invalid]);
        }

        $email = filter_var((string) $request->input('email', ''), FILTER_VALIDATE_EMAIL);
        $password = (string) $request->input('password', '');
        if ($email === false || $password === '') {
            return back()->withErrors('Il modulo non è stato compilato correttamente. Inserisci e-mail e password.');
        }

        // Verifica credenziali come il legacy (prima riga clienti con questa email)
        $cliente = Cliente::where('email', $email)->first();
        if ($cliente === null || ! Hash::check($password, (string) $cliente->password)) {
            return back()->withErrors('Credenziali non valide.');
        }

        Cliente::where('email', $email)->delete();
        DeleteRequest::where('token', $token)->delete();

        return view('public.esito', [
            'title' => 'Account eliminato',
            'text' => 'Il tuo account è stato eliminato definitivamente. Ci dispiace vederti andare via.',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /** Messaggio di errore (testi legacy) o null se il token è valido. */
    private function validateToken(string $token): ?string
    {
        if ($token === '') {
            return 'Token non valido.';
        }
        $req = DeleteRequest::where('token', $token)->first();
        if ($req === null) {
            return 'Richiesta non trovata o già utilizzata.';
        }
        if ($req->expiration !== null && $req->expiration->isPast()) {
            return 'Il link è scaduto.';
        }

        return null;
    }

    private function esito(Request $request, string $titolo, string $testo)
    {
        return redirect($request->path())->with('esito', ['titolo' => $titolo, 'testo' => $testo]);
    }
}
