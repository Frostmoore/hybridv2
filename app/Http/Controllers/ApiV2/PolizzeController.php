<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2;

use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Models\Polizza;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /res/api/v2/polizze/index.php — polizze importate dell'utente (JWT).
 * Porting di legacy res/api/v2/polizze/index.php.
 */
class PolizzeController extends V2Controller
{
    public function index(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        $userId = (int) ($claims['sub'] ?? 0);
        $agencyId = (int) ($claims['agency_id'] ?? 0);

        $user = Cliente::find($userId);
        $cf = strtoupper(trim(ApiResponse::s($user?->cf)));
        if ($cf === '') {
            return ApiResponse::err('Codice fiscale non trovato per questo utente.', 'NOT_FOUND', 404);
        }

        $agency = AgenziaNew::find($agencyId);
        if ($agency === null || ApiResponse::s($agency->token_interno) === '') {
            return ApiResponse::err('Agenzia non configurata per il servizio polizze.', 'NOT_CONFIGURED', 403);
        }

        $s = ApiResponse::s(...);
        $polizze = Polizza::where('cf', $cf)
            ->where('id_agenzia', $agencyId)
            ->orderBy('data_scadenza_titolo')
            ->get()
            ->map(fn (Polizza $p) => [
                'n_polizza' => $s($p->n_polizza),
                'contraente' => $s($p->contraente),
                'compagnia' => $s($p->compagnia),
                'ramo' => $s($p->ramo),
                'prodotto' => $s($p->prodotto),
                'targa' => $s($p->targa),
                'frazionamento' => $s($p->frazionamento),
                'data_decorrenza' => $s($p->data_decorrenza),
                'data_scadenza_titolo' => $s($p->data_scadenza_titolo),
                'data_scadenza_contratto' => $s($p->data_scadenza_contratto),
                'stato_polizza' => $s($p->stato_polizza),
            ])
            ->values()
            ->all();

        // Ordine chiavi come il codice legacy (cf, polizze, count)
        return ApiResponse::ok([
            'cf' => $cf,
            'polizze' => $polizze,
            'count' => count($polizze),
        ]);
    }
}
