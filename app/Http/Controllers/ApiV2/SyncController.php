<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riceve dal VECCHIO server le aggiunte/modifiche da rispecchiare qui durante
 * la transizione (direzione UNICA: vecchio → nuovo). Autenticato con
 * `hybrid.sync_secret` (header X-Sync-Secret). Upsert per `id`: idempotente.
 *
 * Anti-collisione id: le tabelle del nuovo server partono da AUTO_INCREMENT
 * 1.000.000, così gli id "vecchi" (inoltrati) non si sovrappongono ai "nuovi".
 */
class SyncController extends Controller
{
    /** entità accettate → tabella. */
    private const TABLES = [
        'cliente' => 'clienti',
        'agenzia' => 'agenzie_new',
        'notifica' => 'notifiche',
        'operatore' => 'operatori',
    ];

    public function receive(Request $request): JsonResponse
    {
        $secret = (string) config('hybrid.sync_secret');
        $given = (string) $request->header('X-Sync-Secret', '');
        if ($secret === '' || ! hash_equals($secret, $given)) {
            return ApiResponse::err('Non autorizzato.', 'UNAUTHORIZED', 401);
        }

        $body = json_decode((string) $request->getContent(), true);
        $entity = is_array($body) ? (string) ($body['entity'] ?? '') : '';
        $data = is_array($body) ? ($body['data'] ?? null) : null;

        if (! isset(self::TABLES[$entity]) || ! is_array($data)) {
            return ApiResponse::err('Payload non valido (entity/data).', 'VALIDATION_ERROR', 422);
        }

        $table = self::TABLES[$entity];
        // Solo colonne realmente esistenti: niente campi arbitrari.
        $clean = array_intersect_key($data, array_flip(Schema::getColumnListing($table)));

        $id = $clean['id'] ?? null;
        if ($id === null || $id === '') {
            return ApiResponse::err('Campo id obbligatorio.', 'VALIDATION_ERROR', 422);
        }
        unset($clean['id']);

        // Upsert per id (idempotente). Query builder: imposta l'id esplicito,
        // bypassa il mass-assignment guard (sync raw vecchio → nuovo).
        DB::table($table)->updateOrInsert(['id' => $id], $clean);

        return ApiResponse::ok(['entity' => $entity, 'id' => $id, 'synced' => true]);
    }
}
