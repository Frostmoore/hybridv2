<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\LegacyHtmlMail;
use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Support\ApiResponse;
use App\Support\LegacyText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Attivazione account e reset password via link email:
 * /attivacliente.php?a=<id>&b=<token>
 * /cambiapassword.php?a=<id>&b=<token>&c=<id_agenzia>
 * POST res/userpasswordhandler.php (AJAX della pagina cambiapassword)
 *
 * Fix vs legacy (critics.md): userpasswordhandler ora VERIFICA il token
 * del link prima di cambiare la password (il legacy non lo controllava).
 */
class AccountController extends Controller
{
    // ─── GET /attivacliente.php?a&b ──────────────────────────────────────

    public function attivaCliente(Request $request)
    {
        $idUtente = (int) $request->query('a', 0);
        $token = (string) $request->query('b', '');

        $utente = Cliente::find($idUtente);

        if ($utente !== null && ApiResponse::s($utente->active) !== '1') {
            if (hash_equals(ApiResponse::s($utente->activation_token), $token) && $token !== '') {
                $utente->active = '1';
                $utente->save();
                $esito = 'success';
            } else {
                $esito = 'token';
            }
        } elseif ($utente !== null) {
            $esito = 'active';
        } else {
            $esito = 'fail';
        }

        // Testi identici al legacy
        [$title, $text] = match ($esito) {
            'success' => ['Hai attivato con successo il tuo account!', "Ora potrai iniziare a navigare le funzioni avanzate dell'app."],
            'active' => ['Il tuo account è già attivo!', "Puoi già usufruire delle funzioni avanzate dell'app."],
            'token' => ['Il tuo Link di Attivazione è scaduto!', "Te ne verrà inviato uno nuovo quando tenterai di effettuare nuovamente l'accesso all'app."],
            default => ['Si è verificato un errore!', 'Riprova più tardi. Se il problema persiste, contatta un amministratore.'],
        };

        return view('public.esito', ['title' => $title, 'text' => $text]);
    }

    // ─── GET /cambiapassword.php?a&b&c ───────────────────────────────────

    public function cambiaPassword(Request $request)
    {
        $idUtente = (int) $request->query('a', 0);
        $token = (string) $request->query('b', '');
        $idAgenzia = (int) $request->query('c', 0);

        $utente = Cliente::find($idUtente);
        if ($utente === null || $token === '' || ! hash_equals(ApiResponse::s($utente->activation_token), $token)) {
            return view('public.esito', [
                'title' => 'Si è verificato un errore!',
                'text' => 'Il link non è valido o è scaduto. Richiedi un nuovo reset password dall\'app.',
            ]);
        }

        return view('public.cambiapassword', [
            'idUtente' => $idUtente,
            'idAgenzia' => $idAgenzia,
            'token' => $token,
        ]);
    }

    // ─── POST /res/userpasswordhandler.php ───────────────────────────────

    public function userPasswordHandler(Request $request)
    {
        $json = json_decode((string) $request->input('json', ''), true);
        if (! is_array($json)) {
            return response('error');
        }

        $id = (int) ($json['id'] ?? 0);
        $nuovaPassword = (string) ($json['nuova_password'] ?? '');
        $idAgenzia = (int) ($json['id_agenzia'] ?? 0);
        $token = (string) ($json['token'] ?? '');

        $utente = Cliente::find($id);

        // Fix sicurezza: senza token valido non si cambia nulla
        if ($utente === null || $nuovaPassword === '' || $token === ''
            || ! hash_equals(ApiResponse::s($utente->activation_token), $token)) {
            return response('error');
        }

        $utente->password = Hash::make($nuovaPassword);
        $utente->save();

        // Email di conferma (testi legacy), non bloccante
        $agency = AgenziaNew::find($idAgenzia);
        $agencyName = $agency ? (string) $agency->nome_agenzia : 'la tua agenzia';

        try {
            Mail::to(LegacyText::normalizeEmail($utente->email))->send(new LegacyHtmlMail(
                subjectLine: 'Password modificata per l\'utente: '.$utente->username.'.',
                htmlBody: '<h2>Password modificata su '.htmlspecialchars($agencyName).' per l\'utente: '.htmlspecialchars((string) $utente->username).'</h2>'
                    .'<p>Hai reimpostato con successo la tua password su '.htmlspecialchars($agencyName).', accedi all\'app per continuare a navigare.</p>',
                fromName: $agencyName,
            ));
        } catch (\Throwable) {
        }

        return response('success');
    }
}
