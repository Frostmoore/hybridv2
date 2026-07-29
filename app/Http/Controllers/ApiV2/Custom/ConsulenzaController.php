<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2\Custom;

use App\Http\Controllers\ApiV2\Concerns\HandlesClaimSubmissions;
use App\Http\Controllers\ApiV2\V2Controller;
use App\Models\AgenziaNew;
use App\Models\Consulenza;
use App\Support\ApiResponse;
use App\Support\LegacyRowSanitizer;
use App\Support\LegacyText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * POST /res/api/v2/claims/consulenza.php — FUORI CONTRATTO v2.
 *
 * Richiesta di consulenza con appuntamento: stessi campi del preventivo più
 * data e ora richieste. Implementazione speciale per le agenzie che hanno
 * `agenzie_speciale.consulenza_attiva` — per tutte le altre l'endpoint NON
 * ESISTE (404 identico a quello di una route inesistente: chi non è abilitato
 * non deve nemmeno poter dedurre che ci sia).
 *
 * Non documentato in API_V2.md (che è il contratto canonico) ma in
 * API_V2_CUSTOM.md. Nessuna app standard chiama questo path.
 */
class ConsulenzaController extends V2Controller
{
    use HandlesClaimSubmissions;

    /** Allegati accettati (gli stessi del preventivo), tutti opzionali. */
    private const ATTACHMENTS = [
        'documentazione' => 'DOC-',
        'fronteDoc' => 'FDOC-',
        'retroDoc' => 'RDOC-',
    ];

    public function store(Request $request): JsonResponse
    {
        $agencyId = (int) ($this->claims($request)['agency_id'] ?? 0);

        $payload = $this->dataPayload($request);
        if ($payload === null) {
            return ApiResponse::err('Campo POST[data] mancante o JSON non valido.', 'VALIDATION_ERROR', 422);
        }

        $agency = AgenziaNew::find($agencyId);
        if ($agency === null) {
            return ApiResponse::err('Agenzia non trovata.', 'NOT_FOUND', 404);
        }

        // Gate della feature speciale: stesso corpo del 404 di bootstrap/app.php.
        $speciale = $agency->speciale;
        if ($speciale === null || ! $speciale->consulenza_attiva) {
            return ApiResponse::err('Risorsa non trovata.', 'NOT_FOUND', 404);
        }

        $s = ApiResponse::s(...);
        $nome = trim($s($payload['nome'] ?? ''));
        $cognome = trim($s($payload['cognome'] ?? ''));
        $email = LegacyText::normalizeEmail($payload['email'] ?? '');
        $telefono = trim($s($payload['telefono'] ?? ''));
        $indirizzo = trim($s($payload['indirizzo'] ?? ''));
        $descrizione = $s($payload['descrizione'] ?? '');
        $privacy = LegacyRowSanitizer::normalizeBool($payload['privacy'] ?? null) === '1';

        if ($email === '') {
            return ApiResponse::err('Il campo email è obbligatorio.', 'VALIDATION_ERROR', 422);
        }

        $data = trim($s($payload['data_appuntamento'] ?? ''));
        $ora = trim($s($payload['ora_appuntamento'] ?? ''));
        if ($data === '' || $ora === '') {
            return ApiResponse::err('I campi data_appuntamento e ora_appuntamento sono obbligatori.', 'VALIDATION_ERROR', 422);
        }

        $appuntamento = $this->parseAppuntamento($data, $ora);
        if ($appuntamento === null) {
            return ApiResponse::err('Data o ora dell\'appuntamento non valide (attesi YYYY-MM-DD e HH:MM).', 'VALIDATION_ERROR', 422);
        }
        if ($appuntamento->isPast()) {
            return ApiResponse::err('L\'appuntamento non può essere nel passato.', 'VALIDATION_ERROR', 422);
        }

        $agencyName = $s($agency->nome_agenzia);
        $nomecompleto = trim("$cognome $nome");

        $entries = [];
        foreach (self::ATTACHMENTS as $field => $prefix) {
            $entries[$field] = $prefix.$nomecompleto;
        }
        $zipPath = $this->buildZip($request, 'consulenze', $agencyId.'-'.$nomecompleto, $entries) ?: '';

        // Senza allegati ZipArchive NON scrive il file (un archivio vuoto non
        // esiste su disco): senza questo controllo in `documenti` finirebbe il
        // path di un file inesistente. `preventivi`/`documenti`/`sinistri` ce
        // l'hanno da sempre — la mail non ne soffre (LegacyHtmlMail filtra con
        // is_file) ma in tabella resta spazzatura. Qui no.
        if ($zipPath !== '' && ! is_file(Storage::path($zipPath))) {
            $zipPath = '';
        }

        $consulenza = Consulenza::create([
            'id_agenzia' => $agencyId,
            'nome' => $nome,
            'cognome' => $cognome,
            'email' => $email,
            'telefono' => $telefono,
            'indirizzo' => $indirizzo,
            'descrizione' => $descrizione,
            'privacy' => $privacy,
            'appuntamento_il' => $appuntamento,
            'documenti' => $zipPath,
        ]);

        $this->sendClaimMail(
            to: $this->destinationMail($agency, $speciale->consulenza_mail),
            fromName: $agencyName,
            subject: 'Nuova richiesta di consulenza da '.$nomecompleto.' — '.$appuntamento->format('d/m/Y H:i'),
            body: $this->mailBody($nomecompleto, $email, $telefono, $indirizzo, $descrizione, $appuntamento),
            zipPath: $zipPath,
        );

        return ApiResponse::ok([
            'message' => 'Richiesta di consulenza inviata con successo.',
            'id' => (int) $consulenza->id,
        ], 201);
    }

    /**
     * Combina i due campi del date/time picker in un solo istante.
     * Rifiuta i rollover di PHP (2026-02-31 → 03-03) confrontando il
     * riformattato con l'input: se non combacia byte per byte, non è valido.
     */
    private function parseAppuntamento(string $data, string $ora): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1 || preg_match('/^\d{2}:\d{2}$/', $ora) !== 1) {
            return null;
        }

        try {
            $dt = Carbon::createFromFormat('Y-m-d H:i', "$data $ora");
        } catch (\Throwable) {
            return null;
        }

        if ($dt->format('Y-m-d H:i') !== "$data $ora") {
            return null;
        }

        return $dt->startOfMinute();
    }

    /**
     * Casella dedicata dalla tab Speciale; se vuota, la stessa catena dei
     * preventivi (denuncia_mail || quick_email), override di config inclusi.
     */
    private function destinationMail(AgenziaNew $agency, mixed $consulenzaMail): string
    {
        $dedicata = LegacyText::normalizeEmail($consulenzaMail);
        if ($dedicata !== '') {
            return $dedicata;
        }

        $fallback = ApiResponse::s($agency->denuncia_mail) !== '' ? $agency->denuncia_mail : $agency->quick_email;

        return $this->agencyMail((int) $agency->id, 'consulenza', $fallback);
    }

    private function mailBody(
        string $nomecompleto,
        string $email,
        string $telefono,
        string $indirizzo,
        string $descrizione,
        Carbon $appuntamento,
    ): string {
        return '<h2>NUOVA RICHIESTA DI CONSULENZA</h2>'
            .'<p style="font-size:1.1em;"><strong>Appuntamento richiesto:</strong> '
            .htmlspecialchars($appuntamento->format('d/m/Y')).' alle '.htmlspecialchars($appuntamento->format('H:i')).'</p>'
            .'<p><strong>Richiedente:</strong> '.htmlspecialchars($nomecompleto).'</p>'
            .'<p><strong>Email:</strong> '.htmlspecialchars($email).'</p>'
            .'<p><strong>Telefono:</strong> '.htmlspecialchars($telefono).'</p>'
            .'<p><strong>Indirizzo:</strong> '.htmlspecialchars($indirizzo).'</p>'
            .'<p><strong>Richiesta inviata il:</strong> '.date('d/m/Y H:i').'</p>'
            .'<p><strong>Descrizione:</strong> '.nl2br(htmlspecialchars($descrizione)).'</p>';
    }
}
