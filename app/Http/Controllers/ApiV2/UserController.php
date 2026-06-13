<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2;

use App\Models\Cliente;
use App\Support\ApiResponse;
use App\Support\LegacyText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Porting di legacy res/api/v2/user/{me,password,privacy}.php.
 */
class UserController extends V2Controller
{
    private const PRIVACY_COLUMNS = [
        '1' => 'privacyuno',
        '2' => 'privacydue',
        '3' => 'privacytre',
        '4' => 'privacyquattro',
    ];

    // ─── GET|PATCH user/me.php ───────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        $userId = (int) ($claims['sub'] ?? 0);
        $agencyId = (int) ($claims['agency_id'] ?? 0);

        if ($request->isMethod('GET')) {
            $user = Cliente::find($userId);
            if ($user === null) {
                return ApiResponse::err('Utente non trovato.', 'NOT_FOUND', 404);
            }

            // Stesso oggetto del login (17 campi), senza token
            return ApiResponse::ok(AuthController::userPayload($user));
        }

        // PATCH — aggiorna username, email, playerid
        $b = $this->jsonBody($request);
        $updates = [];

        if (array_key_exists('username', $b)) {
            $newUsername = trim(ApiResponse::s($b['username']));
            if ($newUsername === '') {
                return ApiResponse::err('Il campo username non può essere vuoto.', 'VALIDATION_ERROR', 422);
            }
            // Unicità PER AGENZIA, coerente con la registrazione (vedi critics.md)
            $taken = Cliente::where('agenziaid', $agencyId)
                ->where('username', $newUsername)
                ->where('id', '<>', $userId)
                ->exists();
            if ($taken) {
                return ApiResponse::err('Username già in uso.', 'DUPLICATE_USERNAME', 409);
            }
            $updates['username'] = $newUsername;
        }

        if (array_key_exists('email', $b)) {
            $newEmail = LegacyText::normalizeEmail($b['email']);
            if ($newEmail === '') {
                return ApiResponse::err('Il campo email non può essere vuoto.', 'VALIDATION_ERROR', 422);
            }
            $taken = Cliente::where('agenziaid', $agencyId)
                ->where('email', $newEmail)
                ->where('id', '<>', $userId)
                ->exists();
            if ($taken) {
                return ApiResponse::err('Email già in uso.', 'DUPLICATE_EMAIL', 409);
            }
            $updates['email'] = $newEmail;
        }

        if (array_key_exists('playerid', $b)) {
            $updates['playerid'] = ApiResponse::s($b['playerid']);
        }

        if ($updates === []) {
            return ApiResponse::err('Nessun campo modificabile fornito (username, email, playerid).', 'VALIDATION_ERROR', 422);
        }

        Cliente::where('id', $userId)->update($updates);

        return ApiResponse::ok(['message' => 'Profilo aggiornato.']);
    }

    // ─── PATCH user/password.php ─────────────────────────────────────────

    public function password(Request $request): JsonResponse
    {
        $userId = (int) ($this->claims($request)['sub'] ?? 0);
        $b = $this->jsonBody($request);

        $old = ApiResponse::s($b['old_password'] ?? '');
        $new = ApiResponse::s($b['new_password'] ?? '');

        if ($old === '' || $new === '') {
            return ApiResponse::err('I campi old_password e new_password sono obbligatori.', 'VALIDATION_ERROR', 422);
        }
        if (strlen($new) < 8) {
            return ApiResponse::err('La nuova password deve essere di almeno 8 caratteri.', 'VALIDATION_ERROR', 422);
        }

        $user = Cliente::find($userId);
        if ($user === null || ! Hash::check($old, ApiResponse::s($user->password))) {
            return ApiResponse::err('Password attuale non corretta.', 'INVALID_CREDENTIALS', 401);
        }

        $user->password = Hash::make($new);
        $user->save();

        return ApiResponse::ok(['message' => 'Password aggiornata con successo.']);
    }

    // ─── PATCH user/privacy.php ──────────────────────────────────────────

    public function privacy(Request $request): JsonResponse
    {
        $userId = (int) ($this->claims($request)['sub'] ?? 0);
        $b = $this->jsonBody($request);

        $privacyId = ApiResponse::s($b['privacy_id'] ?? '');
        $privacyValue = ($b['privacy_value'] ?? false) ? '1' : '0';

        if (! array_key_exists($privacyId, self::PRIVACY_COLUMNS)) {
            return ApiResponse::err('privacy_id deve essere 1, 2, 3 o 4.', 'VALIDATION_ERROR', 422);
        }

        $value = $privacyValue.'|'.date('Y-m-d H:i:s');
        Cliente::where('id', $userId)->update([self::PRIVACY_COLUMNS[$privacyId] => $value]);

        return ApiResponse::ok(['message' => 'Privacy aggiornata.', 'privacy_id' => $privacyId, 'value' => $value]);
    }
}
