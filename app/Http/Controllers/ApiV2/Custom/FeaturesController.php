<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2\Custom;

use App\Http\Controllers\ApiV2\V2Controller;
use App\Models\AgenziaNew;
use App\Support\ApiResponse;
use App\Support\LegacyText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /res/api/v2/custom/features.php?id&token — FUORI CONTRATTO v2.
 *
 * Espone alle app le feature speciali attive per l'agenzia (tabella
 * `agenzie_speciale`). Endpoint separato di proposito: `agency.php` replica
 * chiave per chiave la SELECT legacy ed è il contratto che leggono TUTTE le app
 * pubblicate, quindi non si tocca. Qui invece siamo liberi, e i booleani sono
 * booleani JSON veri (non le stringhe '1'/'0' del legacy).
 *
 * Stessa autenticazione di agency.php: id + token pubblico dell'agenzia, no JWT
 * (serve prima del login, per decidere quali sezioni disegnare).
 *
 * Agenzia senza riga in `agenzie_speciale` → tutto spento, mai un errore.
 */
class FeaturesController extends V2Controller
{
    public function show(Request $request): JsonResponse
    {
        $agencyId = (int) $request->query('id', '0');
        $agencyToken = ApiResponse::s($request->query('token', ''));

        if ($agencyId <= 0 || $agencyToken === '') {
            return ApiResponse::err('I parametri id e token sono obbligatori.', 'VALIDATION_ERROR', 422);
        }

        $agency = AgenziaNew::find($agencyId);

        // Come agency.php: agenzia inesistente o token errato → stessa 401.
        if ($agency === null || ! hash_equals(ApiResponse::s($agency->token), $agencyToken)) {
            return ApiResponse::err('Token agenzia non valido.', 'UNAUTHORIZED', 401);
        }

        $speciale = $agency->speciale;

        return ApiResponse::ok([
            'consulenza' => [
                'attiva' => (bool) ($speciale->consulenza_attiva ?? false),
                'titolo' => LegacyText::utf8($speciale->consulenza_titolo ?? ''),
                'testo' => LegacyText::utf8($speciale->consulenza_testo ?? ''),
            ],
        ]);
    }
}
