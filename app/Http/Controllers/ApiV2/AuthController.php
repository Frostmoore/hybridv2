<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2;

use App\Mail\LegacyHtmlMail;
use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Services\JwtService;
use App\Support\ApiResponse;
use App\Support\LegacyText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Porting di legacy res/api/v2/auth/{login,register,forgot-password}.php.
 */
class AuthController extends V2Controller
{
    public function __construct(private readonly JwtService $jwt) {}

    /** Oggetto user nel formato/ordine esatto del login legacy. */
    public static function userPayload(Cliente $user, ?string $lastloginOverride = null): array
    {
        $s = ApiResponse::s(...);

        return [
            'id' => $s($user->id),
            'username' => $s($user->username),
            'email' => $s($user->email),
            'nome' => $s($user->nome),
            'cognome' => $s($user->cognome),
            'cf' => $s($user->cf),
            'datadinascita' => $s($user->datadinascita),
            'agenziaid' => $s($user->agenziaid),
            'playerid' => $s($user->playerid),
            'privacy1' => $s($user->privacyuno),
            'privacy2' => $s($user->privacydue),
            'privacy3' => $s($user->privacytre),
            'privacy4' => $s($user->privacyquattro),
            'active' => $s($user->active),
            'firstlogin' => $s($user->firstlogin),
            'lastlogin' => $lastloginOverride ?? $s($user->lastlogin),
            'codiceagenzia' => $s($user->codiceagenzia),
        ];
    }

    /**
     * Lookup identico al login legacy: username, email o CF (case-insensitive).
     */
    public static function findByIdentifier(int $agencyId, string $identifier): ?Cliente
    {
        $lc = strtolower($identifier);
        $uc = strtoupper($identifier);

        return Cliente::where('agenziaid', $agencyId)
            ->where(function (Builder $q) use ($lc, $uc) {
                $q->whereRaw('LOWER(username) = ?', [$lc])
                    ->orWhereRaw('LOWER(email) = ?', [$lc])
                    ->orWhereRaw('UPPER(cf) = ?', [$uc]);
            })
            ->first();
    }

    // ─── POST auth/login.php ─────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $b = $this->jsonBody($request);
        $agencyId = ApiResponse::s($b['agency_id'] ?? '');
        $username = trim(ApiResponse::s($b['username'] ?? ''));
        $password = ApiResponse::s($b['password'] ?? '');

        if ($agencyId === '' || $username === '' || $password === '') {
            return ApiResponse::err('I campi agency_id, username e password sono obbligatori.', 'VALIDATION_ERROR', 422);
        }
        $agencyIdInt = (int) $agencyId;
        if ($agencyIdInt <= 0) {
            return ApiResponse::err('agency_id non valido.', 'VALIDATION_ERROR', 422);
        }

        $user = self::findByIdentifier($agencyIdInt, $username);

        if ($user === null || ! Hash::check($password, ApiResponse::s($user->password))) {
            return ApiResponse::err('Username o password errati.', 'INVALID_CREDENTIALS', 401);
        }

        if (ApiResponse::s($user->active) !== '1') {
            return ApiResponse::err('Account non attivo. Controlla la tua email per il link di attivazione.', 'ACCOUNT_INACTIVE', 403);
        }

        // Come il legacy: la risposta espone firstlogin PRE-aggiornamento
        // e lastlogin = adesso.
        $now = date('Y-m-d H:i:s');
        $payload = self::userPayload($user, lastloginOverride: $now);

        $firstloginWasNull = $user->firstlogin === null;
        $user->lastlogin = $now;
        if ($firstloginWasNull) {
            $user->firstlogin = $now;
        }
        $user->save();

        return ApiResponse::ok([
            'token' => $this->jwt->issueForUser((int) $user->id, ApiResponse::s($user->username), $agencyIdInt),
            'expires_in' => $this->jwt->expiry(),
            'user' => $payload,
        ]);
    }

    // ─── POST auth/register.php ──────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $b = $this->jsonBody($request);

        $agencyId = ApiResponse::s($b['agency_id'] ?? '');
        $username = trim(ApiResponse::s($b['username'] ?? ''));
        $password = ApiResponse::s($b['password'] ?? '');
        $email = LegacyText::normalizeEmail($b['email'] ?? '');
        $nome = trim(ApiResponse::s($b['nome'] ?? ''));
        $cognome = trim(ApiResponse::s($b['cognome'] ?? ''));
        $cf = strtoupper(trim(ApiResponse::s($b['cf'] ?? '')));
        $telefono = ApiResponse::s($b['telefono'] ?? '');
        $ddn = ApiResponse::s($b['datadinascita'] ?? '');
        $playerid = ApiResponse::s($b['playerid'] ?? '');

        if ($agencyId === '' || $username === '' || $password === '' || $email === '') {
            return ApiResponse::err('I campi agency_id, username, password ed email sono obbligatori.', 'VALIDATION_ERROR', 422);
        }
        $agencyIdInt = (int) $agencyId;
        if ($agencyIdInt <= 0) {
            return ApiResponse::err('agency_id non valido.', 'VALIDATION_ERROR', 422);
        }

        // Flag privacy con timestamp appeso (formato legacy "v|Y-m-d H:i:s")
        $now = date('Y-m-d H:i:s');
        $privacy = [];
        foreach (['privacy1', 'privacy2', 'privacy3', 'privacy4'] as $key) {
            $privacy[$key] = ApiResponse::s($b[$key] ?? '').'|'.$now;
        }

        // Unicità per agenzia, scandita riga per riga come il legacy
        // (ordine dei controlli: cf → username → email).
        $existing = Cliente::where('agenziaid', $agencyIdInt)->get(['cf', 'username', 'email']);
        foreach ($existing as $row) {
            if ($cf !== '' && strtoupper(ApiResponse::s($row->cf)) === $cf) {
                return ApiResponse::err('Codice fiscale già registrato.', 'DUPLICATE_CF', 409);
            }
            if (ApiResponse::s($row->username) === $username) {
                return ApiResponse::err('Username già in uso.', 'DUPLICATE_USERNAME', 409);
            }
            if (LegacyText::normalizeEmail($row->email) === $email) {
                return ApiResponse::err('Email già registrata.', 'DUPLICATE_EMAIL', 409);
            }
        }

        $activationToken = bin2hex(random_bytes(16));

        $user = DB::transaction(fn () => Cliente::create([
            'username' => $username,
            'password' => Hash::make($password),
            'email' => $email,
            'telefono' => $telefono,
            'nome' => $nome,
            'cognome' => $cognome,
            'cf' => $cf,
            'datadinascita' => $ddn,
            'agenziaid' => $agencyIdInt,
            'playerid' => $playerid,
            'privacyuno' => $privacy['privacy1'],
            'privacydue' => $privacy['privacy2'],
            'privacytre' => $privacy['privacy3'],
            'privacyquattro' => $privacy['privacy4'],
            'active' => '0',
            'activation_token' => $activationToken,
        ]));

        $agency = AgenziaNew::find($agencyIdInt);
        $agencyName = $agency ? ApiResponse::s($agency->nome_agenzia) : 'la tua agenzia';
        $agencyEmail = $agency ? LegacyText::normalizeEmail($agency->info_email_sedi) : '';

        $activateUrl = rtrim((string) config('app.url'), '/')
            .'/attivacliente.php?a='.urlencode((string) $user->id).'&b='.urlencode($activationToken);

        // Email attivazione all'utente (non bloccante, come il legacy)
        try {
            Mail::to($email)->send(new LegacyHtmlMail(
                subjectLine: 'Attiva il tuo account su '.$agencyName.'.',
                htmlBody: '<h2>Attiva il tuo account su '.htmlspecialchars($agencyName, ENT_QUOTES, 'UTF-8').'</h2>'
                    .'<p>Hai completato la registrazione. Clicca sul link per attivare il tuo account:</p>'
                    .'<p><a href="'.$activateUrl.'">Attiva il tuo account</a></p>',
                fromName: $agencyName,
            ));
        } catch (\Throwable) {
            // registrato comunque, email non partita
        }

        // Avviso all'agenzia (non bloccante)
        if ($agencyEmail !== '') {
            try {
                Mail::to($agencyEmail)->send(new LegacyHtmlMail(
                    subjectLine: 'Nuova registrazione su '.$agencyName,
                    htmlBody: '<h3>Nuova registrazione ricevuta</h3><ul>'
                        .'<li>Username: '.htmlspecialchars($username, ENT_QUOTES, 'UTF-8').'</li>'
                        .'<li>Email: '.htmlspecialchars($email, ENT_QUOTES, 'UTF-8').'</li>'
                        .'<li>Nome: '.htmlspecialchars("$nome $cognome", ENT_QUOTES, 'UTF-8').'</li>'
                        .'</ul>',
                    fromName: $agencyName,
                ));
            } catch (\Throwable) {
            }
        }

        return ApiResponse::ok(['message' => 'Registrazione completata. Controlla la tua email per attivare l\'account.'], 201);
    }

    // ─── POST auth/forgot-password.php ───────────────────────────────────

    /**
     * Legacy: accettava solo {agency_id, cf}. Esteso (decisione §7.1) per
     * accettare anche {agency_id, username} con lookup come il login,
     * perché l'app Flutter manda `username`.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $b = $this->jsonBody($request);
        $agencyId = (int) ApiResponse::s($b['agency_id'] ?? '0');
        $cf = strtoupper(trim(ApiResponse::s($b['cf'] ?? '')));
        $username = trim(ApiResponse::s($b['username'] ?? ''));

        if ($agencyId <= 0 || ($cf === '' && $username === '')) {
            return ApiResponse::err('I campi agency_id e cf sono obbligatori.', 'VALIDATION_ERROR', 422);
        }

        $user = $cf !== ''
            ? Cliente::where('agenziaid', $agencyId)->whereRaw('UPPER(cf) = ?', [$cf])->first()
            : self::findByIdentifier($agencyId, $username);

        $genericMessage = 'Se il codice fiscale è registrato, riceverai una email con le istruzioni.';

        // Risposta generica per non rivelare se l'identificativo esiste
        if ($user === null) {
            return ApiResponse::ok(['message' => $genericMessage]);
        }

        $agency = AgenziaNew::find($agencyId);
        $agencyName = $agency ? ApiResponse::s($agency->nome_agenzia) : 'la tua agenzia';

        $resetUrl = rtrim((string) config('app.url'), '/')
            .'/cambiapassword.php?a='.urlencode((string) $user->id)
            .'&b='.urlencode(ApiResponse::s($user->activation_token))
            .'&c='.urlencode((string) $agencyId);

        try {
            Mail::to(LegacyText::normalizeEmail($user->email))->send(new LegacyHtmlMail(
                subjectLine: 'Reimposta la tua password su '.$agencyName,
                htmlBody: '<h2>Richiesta di reimpostazione password</h2>'
                    .'<p>Abbiamo ricevuto una richiesta di reimpostazione password. '
                    .'Se non sei stato tu, ignora questa email.</p>'
                    .'<p><a href="'.$resetUrl.'">Reimposta la tua password</a></p>',
                fromName: $agencyName,
            ));
        } catch (\Throwable) {
            return ApiResponse::err('Impossibile inviare l\'email. Riprova più tardi.', 'MAIL_ERROR', 500);
        }

        return ApiResponse::ok(['message' => $genericMessage]);
    }
}
