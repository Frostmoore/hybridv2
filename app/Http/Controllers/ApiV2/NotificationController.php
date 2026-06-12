<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2;

use App\Models\Notifica;
use App\Models\NotificaGenerale;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Porting di legacy res/api/v2/notifications/{index,general,single,read}.php.
 *
 * NOTA: index e single nel legacy leggevano colonne inesistenti (notifica_*)
 * ed erano rotti in produzione. Qui sono riscritti sullo schema REALE della
 * tabella `notifiche` (titolo, contenuto→testo, link, immagine, dataora),
 * mantenendo il formato di output documentato in API_V2.md. In più, index
 * filtra per agenzia del JWT (il legacy non filtrava: vedi critics.md).
 */
class NotificationController extends V2Controller
{
    /** Output di una notifica privata nel formato documentato. */
    private function payload(Notifica $n, string $username): array
    {
        $s = ApiResponse::s(...);

        return [
            'id' => $s($n->id),
            'titolo' => $s($n->titolo),
            'testo' => $s($n->contenuto),
            'link' => $s($n->link),
            'immagine' => $s($n->immagine),
            'dataora' => $s($n->dataora),
            'letta' => $n->isLettaDa($username),
        ];
    }

    // ─── GET notifications/index.php ─────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        $username = strtolower(trim(ApiResponse::s($claims['username'] ?? '')));
        $agencyId = (int) ($claims['agency_id'] ?? 0);

        if ($username === '') {
            return ApiResponse::err('Token non contiene username.', 'UNAUTHORIZED', 401);
        }

        $rows = Notifica::where('agenziaid', $agencyId)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $result = [];
        foreach ($rows as $n) {
            $destinatari = array_map('strtolower', $n->destinatariList());
            if (! in_array($username, $destinatari, true)) {
                continue;
            }
            $result[] = $this->payload($n, $username);
        }

        return ApiResponse::ok($result);
    }

    // ─── GET notifications/general.php?agency_id ─────────────────────────

    public function general(Request $request): JsonResponse
    {
        $agencyId = (int) $request->query('agency_id', '0');
        if ($agencyId <= 0) {
            return ApiResponse::err('Il parametro agency_id è obbligatorio.', 'VALIDATION_ERROR', 422);
        }

        $s = ApiResponse::s(...);
        $rows = NotificaGenerale::where('notifica_agid', $agencyId)
            ->where('notifica_scadenza', '>=', date('Y-m-d'))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return ApiResponse::ok($rows->map(fn (NotificaGenerale $r) => [
            'id' => $s($r->id),
            'titolo' => $s($r->notifica_titolo),
            'testo' => $s($r->notifica_testo),
            'link' => $s($r->notifica_link),
            'immagine' => $s($r->notifica_immagine),
            'scadenza' => $s($r->notifica_scadenza),
        ])->values()->all());
    }

    // ─── GET notifications/single.php?id ─────────────────────────────────

    public function single(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        $username = strtolower(trim(ApiResponse::s($claims['username'] ?? '')));

        $notifId = (int) $request->query('id', '0');
        if ($notifId <= 0) {
            return ApiResponse::err('Il parametro id è obbligatorio.', 'VALIDATION_ERROR', 422);
        }

        $n = Notifica::find($notifId);
        if ($n === null) {
            return ApiResponse::err('Notifica non trovata.', 'NOT_FOUND', 404);
        }

        return ApiResponse::ok($this->payload($n, $username));
    }

    // ─── POST notifications/read.php ─────────────────────────────────────

    public function read(Request $request): JsonResponse
    {
        $claims = $this->claims($request);
        $username = strtolower(trim(ApiResponse::s($claims['username'] ?? '')));

        $b = $this->jsonBody($request);
        $notifId = (int) ApiResponse::s($b['id'] ?? '0');

        if ($notifId <= 0 || $username === '') {
            return ApiResponse::err('Il campo id è obbligatorio.', 'VALIDATION_ERROR', 422);
        }

        $n = Notifica::find($notifId);
        if ($n === null) {
            return ApiResponse::err('Notifica non trovata.', 'NOT_FOUND', 404);
        }

        $readers = $n->lettaDaList();
        if (! in_array($username, $readers, true)) {
            $readers[] = $username;
            // Virgola finale come il legacy
            $n->letta_da = implode(',', $readers).',';
            $n->save();
        }

        return ApiResponse::ok(['id' => $notifId, 'letta' => true]);
    }
}
