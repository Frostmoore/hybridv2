<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2;

use App\Models\AgenziaNew;
use App\Support\ApiResponse;
use App\Support\LegacyText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /res/api/v2/agency.php?id&token — config agenzia per l'app white-label.
 * Porting di legacy res/api/v2/agency.php (stesse colonne, stesso ordine chiavi).
 */
class AgencyController extends V2Controller
{
    /** Colonne esposte, nell'ordine esatto della SELECT legacy. */
    private const FIELDS = [
        'nome_app', 'nome_agenzia', 'logo_agenzia', 'header_agenzia', 'colori',
        'facebook_agenzia', 'instagram_agenzia', 'linkedin_agenzia', 'google_agenzia', 'sito_agenzia',
        'info_titolo', 'info_immagine', 'info_nomi_sedi', 'info_indirizzi_sedi', 'info_testo_orari',
        'info_orari_sedi', 'info_recensioni_sedi', 'info_telefono_sedi', 'info_email_sedi',
        'info_mappa_sedi', 'info_sito_sedi', 'notifica_titolo', 'notifica_testo', 'notifica_link',
        'notifica_immagine', 'contatti_immagine', 'contatti_titolo', 'numeri_utili_labels',
        'numeri_utili_colori', 'numeri_utili_salute', 'numeri_utili_assistenza', 'numeri_utili_noleggio',
        'denuncia_immagine', 'denuncia_titolo', 'denuncia_testo_grassetto', 'preventivo_immagine',
        'preventivo_testo_grassetto', 'preventivo_titolo', 'documento_immagine',
        'documento_testo_grassetto', 'documento_titolo', 'quick_telefono', 'quick_whatsapp',
        'quick_email', 'attiva', 'denuncia_mail', 'codiceagenzia', 'privacy_agenzia',
        'assisecret', 'assiurl', 'sintesi_token', 'sintesi_lic', 'sintesi_azi', 'sintesi_age',
        'os_app_id', 'os_api_key',
    ];

    public function show(Request $request): JsonResponse
    {
        $agencyId = (int) $request->query('id', '0');
        $agencyToken = ApiResponse::s($request->query('token', ''));

        if ($agencyId <= 0 || $agencyToken === '') {
            return ApiResponse::err('I parametri id e token sono obbligatori.', 'VALIDATION_ERROR', 422);
        }

        $agency = AgenziaNew::find($agencyId);

        // Come il legacy: agenzia inesistente o token errato → stessa 401.
        if ($agency === null || ! hash_equals(ApiResponse::s($agency->token), $agencyToken)) {
            return ApiResponse::err('Token agenzia non valido.', 'UNAUTHORIZED', 401);
        }

        $clean = [];
        foreach (self::FIELDS as $field) {
            $clean[$field] = LegacyText::utf8($agency->{$field});
        }

        return ApiResponse::ok($clean);
    }
}
