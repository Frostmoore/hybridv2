<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2;

use App\Http\Controllers\ApiV2\Concerns\HandlesClaimSubmissions;
use App\Models\AgenziaNew;
use App\Models\Documento;
use App\Models\Preventivo;
use App\Models\Sinistro;
use App\Support\ApiResponse;
use App\Support\LegacyRowSanitizer;
use App\Support\LegacyText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Porting di legacy res/api/v2/claims/{sinistro,preventivo,documento}.php.
 * Multipart: payload JSON nel campo POST `data`, allegati come file separati.
 * Gli ZIP vanno in storage/app/private/uploads/{sinistri,preventivi,documenti}
 * (il legacy usava res/api/v2/uploads/, mai esposti via URL).
 *
 * dataPayload/buildZip/sendClaimMail/agencyMail vivono in
 * HandlesClaimSubmissions: sono condivisi con gli endpoint custom.
 */
class ClaimsController extends V2Controller
{
    use HandlesClaimSubmissions;

    // ─── POST claims/sinistro.php ────────────────────────────────────────

    public function sinistro(Request $request): JsonResponse
    {
        $agencyId = (int) ($this->claims($request)['agency_id'] ?? 0);

        $payload = $this->dataPayload($request);
        if ($payload === null) {
            return ApiResponse::err('Campo POST[data] mancante o JSON non valido.', 'VALIDATION_ERROR', 422);
        }

        $option = (int) ($payload['option'] ?? 0);
        if (! in_array($option, [1, 2, 3], true)) {
            return ApiResponse::err('Il campo option deve essere 1 (auto CAI compilato), 2 (auto CAI non compilato) o 3 (non auto).', 'VALIDATION_ERROR', 422);
        }

        $agency = AgenziaNew::find($agencyId);
        if ($agency === null) {
            return ApiResponse::err('Agenzia non trovata.', 'NOT_FOUND', 404);
        }

        $agencyName = ApiResponse::s($agency->nome_agenzia);
        $denunciaMail = $this->agencyMail($agencyId, 'denuncia', $agency->denuncia_mail);

        $s = ApiResponse::s(...);
        $privacy = LegacyRowSanitizer::normalizeBool($payload['privacy'] ?? null);

        if ($option === 1) {
            $nomecompleto = trim($s($payload['cognome'] ?? '').' '.$s($payload['nome'] ?? ''));
            $emailUtente = LegacyText::normalizeEmail($payload['email'] ?? '');
            $tipoSinistro = 'Auto (CAI Compilato)';
            $dataSinistro = $s($payload['dataSinistro'] ?? date('Y-m-d'));
            $descrizione = $s($payload['descrizione'] ?? '');
            $files = ['fotoCAI' => 'CAI-', 'fronteDoc' => 'FDOC-', 'retroDoc' => 'RDOC-'];

            $body = '<h2>NUOVA DENUNCIA SINISTRO AUTO — CAI COMPILATO</h2>'
                .'<p><strong>Denunciante:</strong> '.htmlspecialchars($nomecompleto).'</p>'
                .'<p><strong>Email:</strong> '.htmlspecialchars($emailUtente).'</p>'
                .'<p><strong>Data sinistro:</strong> '.htmlspecialchars($dataSinistro).'</p>'
                .'<p><strong>Descrizione:</strong> '.nl2br(htmlspecialchars($descrizione)).'</p>';
        } elseif ($option === 2) {
            $contraente = (array) ($payload['contraente'] ?? []);
            $veicoloA = (array) ($payload['veicoloA'] ?? []);
            $veicoloB = (array) ($payload['veicoloB'] ?? []);

            $nomecompleto = trim($s($contraente['cognome'] ?? '').' '.$s($contraente['nome'] ?? ''));
            $emailUtente = LegacyText::normalizeEmail($contraente['email'] ?? '');
            $tipoSinistro = 'Auto (CAI non Compilato)';
            $dataSinistro = $s($payload['dataOraIncidente'] ?? date('Y-m-d H:i'));
            $descrizione = $s($payload['descrizione'] ?? '');
            $files = ['fronteDoc' => 'FDOC-', 'retroDoc' => 'RDOC-'];

            $body = '<h2>NUOVA DENUNCIA SINISTRO AUTO — CAI NON COMPILATO</h2>'
                .'<p><strong>Denunciante:</strong> '.htmlspecialchars($nomecompleto).'</p>'
                .'<p><strong>Email:</strong> '.htmlspecialchars($emailUtente).'</p>'
                .'<p><strong>Data/Ora incidente:</strong> '.htmlspecialchars($dataSinistro).'</p>'
                .'<p><strong>Luogo:</strong> '.htmlspecialchars($s($payload['luogoIncidente'] ?? '')).'</p>'
                .'<p><strong>Feriti:</strong> '.htmlspecialchars($s($payload['feriti'] ?? '')).'</p>'
                .'<p><strong>Descrizione:</strong> '.nl2br(htmlspecialchars($descrizione)).'</p>'
                .'<h3>Veicolo A</h3>'
                .'<p>Targa: '.htmlspecialchars($s($veicoloA['targaTelaio'] ?? '')).' — Marca: '.htmlspecialchars($s($veicoloA['marca'] ?? '')).'</p>'
                .'<h3>Veicolo B</h3>'
                .'<p>Targa: '.htmlspecialchars($s($veicoloB['targaTelaio'] ?? '')).'</p>';
        } else {
            $nomecompleto = trim($s($payload['cognome'] ?? '').' '.$s($payload['nome'] ?? ''));
            $emailUtente = LegacyText::normalizeEmail($payload['email'] ?? '');
            $tipoSinistro = 'Non Auto';
            $dataSinistro = $s($payload['dataSinistro'] ?? date('Y-m-d'));
            $descrizione = $s($payload['descrizione'] ?? '');
            $files = ['documentazione' => 'DOC-', 'fronteDoc' => 'FDOC-', 'retroDoc' => 'RDOC-'];

            $body = '<h2>NUOVA DENUNCIA SINISTRO NON AUTO</h2>'
                .'<p><strong>Denunciante:</strong> '.htmlspecialchars($nomecompleto).'</p>'
                .'<p><strong>Email:</strong> '.htmlspecialchars($emailUtente).'</p>'
                .'<p><strong>Data sinistro:</strong> '.htmlspecialchars($dataSinistro).'</p>'
                .'<p><strong>Descrizione:</strong> '.nl2br(htmlspecialchars($descrizione)).'</p>';
        }

        // Insert prima dello ZIP: l'id (AUTO_INCREMENT) serve per nome file
        // ed entry, come il MAX(id)+1 del legacy.
        $sinistro = Sinistro::create([
            'id_agenzia' => $agencyId,
            'nome_denuncia' => $nomecompleto,
            'tipo_sinistro' => $tipoSinistro,
            'email_denuncia' => $emailUtente,
            'documenti_denuncia' => '',
            'data_denuncia' => $dataSinistro,
            'descrizione_denuncia' => $descrizione,
            'privacy_denuncia' => $privacy,
        ]);
        $sinistroId = (int) $sinistro->id;

        $entries = [];
        foreach ($files as $field => $prefix) {
            $entries[$field] = $sinistroId.$prefix.$nomecompleto;
        }
        $zipPath = $this->buildZip($request, 'sinistri', $agencyId.'-'.$sinistroId, $entries);
        if ($zipPath === false) {
            $sinistro->delete();

            return ApiResponse::err('Impossibile creare l\'archivio ZIP.', 'ZIP_ERROR', 500);
        }

        $sinistro->documenti_denuncia = $zipPath;
        $sinistro->save();

        $this->sendClaimMail(
            to: $denunciaMail,
            fromName: $agencyName,
            subject: 'Nuova denuncia sinistro da '.$nomecompleto.' — '.date('Y-m-d H:i'),
            body: $body.'<p><em>Documentazione in allegato.</em></p>',
            zipPath: $zipPath,
        );

        return ApiResponse::ok(['message' => 'Denuncia inviata con successo.', 'id' => $sinistroId], 201);
    }

    // ─── POST claims/preventivo.php ──────────────────────────────────────

    public function preventivo(Request $request): JsonResponse
    {
        $agencyId = (int) ($this->claims($request)['agency_id'] ?? 0);

        $payload = $this->dataPayload($request);
        if ($payload === null) {
            return ApiResponse::err('Campo POST[data] mancante o JSON non valido.', 'VALIDATION_ERROR', 422);
        }

        $s = ApiResponse::s(...);
        $nome = trim($s($payload['nome'] ?? ''));
        $cognome = trim($s($payload['cognome'] ?? ''));
        $email = LegacyText::normalizeEmail($payload['email'] ?? '');
        $telefono = $s($payload['telefono'] ?? '');
        $indirizzo = $s($payload['indirizzo'] ?? '');
        $descrizione = $s($payload['descrizione'] ?? '');
        $privacy = LegacyRowSanitizer::normalizeBool($payload['privacy'] ?? null);

        if ($email === '') {
            return ApiResponse::err('Il campo email è obbligatorio.', 'VALIDATION_ERROR', 422);
        }

        $agency = AgenziaNew::find($agencyId);
        if ($agency === null) {
            return ApiResponse::err('Agenzia non trovata.', 'NOT_FOUND', 404);
        }

        $agencyName = $s($agency->nome_agenzia);
        $fallbackMail = $s($agency->denuncia_mail) !== '' ? $agency->denuncia_mail : $agency->quick_email;
        $destMail = $this->agencyMail($agencyId, 'preventivo', $fallbackMail);

        $nomecompleto = trim("$cognome $nome");
        $zipPath = $this->buildZip($request, 'preventivi', $agencyId.'-'.$nomecompleto, [
            'documentazione' => 'DOC-'.$nomecompleto,
            'fronteDoc' => 'FDOC-'.$nomecompleto,
            'retroDoc' => 'RDOC-'.$nomecompleto,
        ]) ?: '';

        $oggi = date('Y-m-d H:i:s');
        Preventivo::create([
            'id_agenzia' => $agencyId,
            'nome_denuncia' => $nomecompleto,
            'email_denuncia' => $email,
            'documenti_denuncia' => $zipPath,
            'data_denuncia' => $oggi,
            'descrizione_denuncia' => $descrizione,
            'privacy_denuncia' => $privacy,
        ]);

        $body = '<h2>NUOVA RICHIESTA DI PREVENTIVO</h2>'
            .'<p><strong>Richiedente:</strong> '.htmlspecialchars($nomecompleto).'</p>'
            .'<p><strong>Email:</strong> '.htmlspecialchars($email).'</p>'
            .'<p><strong>Telefono:</strong> '.htmlspecialchars($telefono).'</p>'
            .'<p><strong>Indirizzo:</strong> '.htmlspecialchars($indirizzo).'</p>'
            .'<p><strong>Data:</strong> '.$oggi.'</p>'
            .'<p><strong>Descrizione:</strong> '.nl2br(htmlspecialchars($descrizione)).'</p>';

        $this->sendClaimMail(
            to: $destMail,
            fromName: $agencyName,
            subject: 'Nuova richiesta preventivo da '.$nomecompleto.' — '.date('Y-m-d H:i'),
            body: $body,
            zipPath: $zipPath,
        );

        return ApiResponse::ok(['message' => 'Richiesta preventivo inviata con successo.'], 201);
    }

    // ─── POST claims/documento.php ───────────────────────────────────────

    public function documento(Request $request): JsonResponse
    {
        $agencyId = (int) ($this->claims($request)['agency_id'] ?? 0);

        $payload = $this->dataPayload($request);
        if ($payload === null) {
            return ApiResponse::err('Campo POST[data] mancante o JSON non valido.', 'VALIDATION_ERROR', 422);
        }

        $s = ApiResponse::s(...);
        $nome = trim($s($payload['nome'] ?? ''));
        $cognome = trim($s($payload['cognome'] ?? ''));
        $email = LegacyText::normalizeEmail($payload['email'] ?? '');
        $descrizione = $s($payload['descrizione'] ?? '');
        $privacy = LegacyRowSanitizer::normalizeBool($payload['privacy'] ?? null);

        if ($email === '') {
            return ApiResponse::err('Il campo email è obbligatorio.', 'VALIDATION_ERROR', 422);
        }

        $agency = AgenziaNew::find($agencyId);
        if ($agency === null) {
            return ApiResponse::err('Agenzia non trovata.', 'NOT_FOUND', 404);
        }

        $agencyName = $s($agency->nome_agenzia);
        $destMail = $this->agencyMail($agencyId, 'documento', $agency->quick_email);

        $nomecompleto = trim("$cognome $nome");
        $zipPath = $this->buildZip($request, 'documenti', $agencyId.'-'.$nomecompleto, [
            'documentazione' => 'DOC-'.$nomecompleto,
        ]) ?: '';

        $oggi = date('Y-m-d H:i:s');
        Documento::create([
            'id_agenzia' => $agencyId,
            'nome_denuncia' => $nomecompleto,
            'email_denuncia' => $email,
            'documenti_denuncia' => $zipPath,
            'data_denuncia' => $oggi,
            'descrizione_denuncia' => $descrizione,
            'privacy_denuncia' => $privacy,
        ]);

        $body = '<h2>NUOVO DOCUMENTO CARICATO</h2>'
            .'<p><strong>Mittente:</strong> '.htmlspecialchars($nomecompleto).'</p>'
            .'<p><strong>Email:</strong> '.htmlspecialchars($email).'</p>'
            .'<p><strong>Data:</strong> '.$oggi.'</p>'
            .'<p><strong>Descrizione:</strong> '.nl2br(htmlspecialchars($descrizione)).'</p>';

        $this->sendClaimMail(
            to: $destMail,
            fromName: $agencyName,
            subject: 'Nuovo documento da '.$nomecompleto.' — '.date('Y-m-d H:i'),
            body: $body,
            zipPath: $zipPath,
        );

        return ApiResponse::ok(['message' => 'Documento inviato con successo.'], 201);
    }
}
