<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agencies;

use App\Http\Controllers\Controller;
use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Models\Notifica;
use App\Models\NotificaGenerale;
use App\Services\OneSignalService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

/**
 * Notifiche del pannello agencies: 3 pagine + 3 handler
 * api/v1/send_notification_{all,private,selected}.php.
 * Porting fedele (insert su notifiche/notifiche_generali + push OneSignal).
 */
class AgenciesNotificationController extends Controller
{
    public function __construct(private readonly OneSignalService $oneSignal) {}

    // ─── Pagine ──────────────────────────────────────────────────────────

    public function pageAll()
    {
        return view('agencies.notification_all', $this->pageData());
    }

    public function pagePrivate()
    {
        return view('agencies.notification_private', $this->pageData());
    }

    public function pageSelected()
    {
        return view('agencies.notification_selected', $this->pageData());
    }

    /** @return array<string, mixed> */
    private function pageData(): array
    {
        $operatore = Auth::guard('operatore')->user();
        $agid = (int) $operatore->agid;

        return [
            'operatore' => $operatore,
            'agid' => $agid,
            'clienti' => Cliente::where('agenziaid', $agid)
                ->whereNotNull('playerid')->where('playerid', '<>', '')
                ->orderBy('cognome')
                ->get(['id', 'username', 'nome', 'cognome', 'playerid']),
        ];
    }

    // ─── Inviate: elenco + gestione ──────────────────────────────────────

    public function pageSent()
    {
        $operatore = Auth::guard('operatore')->user();
        $agid = (int) $operatore->agid;

        return view('agencies.notification_sent', [
            'operatore' => $operatore,
            'agid' => $agid,
            'generali' => NotificaGenerale::where('notifica_agid', $agid)->orderByDesc('id')->get(),
            'mirate' => Notifica::where('agenziaid', $agid)->orderByDesc('id')->get(),
        ]);
    }

    public function deleteGenerale(int $id)
    {
        $agid = (int) Auth::guard('operatore')->user()->agid;
        NotificaGenerale::where('id', $id)->where('notifica_agid', $agid)->delete();

        return redirect('/notifiche/inviate')->with('status', 'Notifica generale eliminata.');
    }

    public function deleteMirata(int $id)
    {
        $agid = (int) Auth::guard('operatore')->user()->agid;
        Notifica::where('id', $id)->where('agenziaid', $agid)->delete();

        return redirect('/notifiche/inviate')->with('status', 'Notifica eliminata.');
    }

    public function updateScadenza(Request $request, int $id)
    {
        $agid = (int) Auth::guard('operatore')->user()->agid;
        $scadenza = str_replace('T', ' ', trim((string) $request->input('notifica_scadenza', '')));

        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $scadenza)) {
            return redirect('/notifiche/inviate')->with('status', 'Scadenza non valida.');
        }
        if (strlen($scadenza) === 16) {
            $scadenza .= ':00';
        }
        NotificaGenerale::where('id', $id)->where('notifica_agid', $agid)
            ->update(['notifica_scadenza' => $scadenza]);

        return redirect('/notifiche/inviate')->with('status', 'Scadenza aggiornata.');
    }

    // ─── POST api/v1/send_notification_all.php ───────────────────────────

    public function sendAll(Request $request)
    {
        $titolo = trim((string) $request->input('titolo', ''));
        $testo = trim((string) $request->input('testo', ''));
        $media = trim((string) $request->input('media', ''));
        $link = trim((string) $request->input('link', ''));
        $agid = $request->input('agenziaid');
        $scadenza = (string) $request->input('notifica_scadenza', '');

        if ($titolo === '' || $testo === '' || $scadenza === '') {
            return response()->json(['success' => false, 'message' => 'Tutti i campi obbligatori devono essere compilati.']);
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $scadenza)) {
            return response()->json(['success' => false, 'message' => 'Data di scadenza non valida.']);
        }

        // Upload immagine → /uploads/<nome> (URL pubblico sul dominio agencies)
        $uploaded = $this->saveImage($request, 'notifica_immagine', public_path('uploads'), 'uploads', 20);
        if (is_array($uploaded)) {
            return response()->json($uploaded);
        }
        if ($uploaded !== null) {
            $media = $uploaded;
        }

        if ($media !== '' && $uploaded === null) {
            if (! filter_var($media, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => "L'URL dell'immagine non è valido."]);
            }
            if (! preg_match('/\.(jpg|jpeg|png|webp)$/i', $media)) {
                return response()->json(['success' => false, 'message' => "L'immagine deve essere un file JPG, PNG o WEBP."]);
            }
        }

        if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'URL non valido.']);
        }
        $testolink = $link !== '' ? 'Visita Ora!' : null;

        $usernames = Cliente::where('agenziaid', $agid)->pluck('username')->all();

        $osConfig = AgenziaNew::find($agid);
        if ($osConfig === null || ! $osConfig->os_app_id || ! $osConfig->os_api_key) {
            return response()->json(['success' => false, 'message' => 'Configurazione OneSignal mancante per questa agenzia.']);
        }

        // Compatibilità vecchia: salva anche su notifiche (come il legacy)
        Notifica::create([
            'titolo' => $titolo,
            'contenuto' => $testo,
            'immagine' => $media,
            'destinatari' => implode(',', $usernames),
            'link' => $link,
            'testolink' => $testolink,
            'dataora' => now()->format('Y-m-d H:i:s'),
            'agenziaid' => $agid,
        ]);

        NotificaGenerale::create([
            'notifica_titolo' => $titolo,
            'notifica_testo' => $testo,
            'notifica_link' => $link,
            'notifica_immagine' => $media,
            'notifica_scadenza' => $scadenza,
            'notifica_agid' => $agid,
        ]);

        $payload = $this->oneSignal->buildPayload((string) $osConfig->os_app_id, $titolo, $testo, null, $media ?: null);
        [$status, $body] = $this->oneSignal->send((string) $osConfig->os_api_key, $payload);

        if ($status !== 200) {
            return response()->json(['success' => false, 'message' => 'Errore nell\'invio della notifica al gestore notifiche: '.$body]);
        }

        return response()->json(['success' => true, 'message' => 'Notifica inviata con successo a tutti gli utenti dell\'agenzia!']);
    }

    // ─── POST api/v1/send_notification_private.php ───────────────────────

    public function sendPrivate(Request $request)
    {
        $playerid = $request->input('playerid');
        $titolo = trim((string) $request->input('titolo', ''));
        $testo = trim((string) $request->input('testo', ''));
        $link = trim((string) $request->input('link', ''));
        $agid = $request->input('agenziaid');

        if (! $playerid || $titolo === '' || $testo === '') {
            return response()->json(['success' => false, 'message' => 'Tutti i campi obbligatori devono essere compilati.']);
        }
        if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'URL non valido.']);
        }
        $testolink = $link !== '' ? 'Visita Ora!' : null;

        $imageUrl = $this->saveImage($request, 'media', public_path('includes/images'), 'includes/images', 10);
        if (is_array($imageUrl)) {
            return response()->json($imageUrl);
        }

        $user = Cliente::where('playerid', $playerid)->first();
        if ($user === null) {
            return response()->json(['success' => false, 'message' => 'Destinatario non trovato.']);
        }

        Notifica::create([
            'destinatari' => $user->username,
            'titolo' => $titolo,
            'contenuto' => $testo,
            'immagine' => $imageUrl ?? '',
            'link' => $link,
            'testolink' => $testolink,
            'dataora' => now()->format('Y-m-d H:i:s'),
            'agenziaid' => $agid,
        ]);

        $error = $this->push($agid, $titolo, $testo, [(string) $playerid], $imageUrl);
        if ($error !== null) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        return response()->json(['success' => true, 'message' => 'Notifica salvata e inviata con successo!']);
    }

    // ─── POST api/v1/send_notification_selected.php ──────────────────────

    public function sendSelected(Request $request)
    {
        $playerids = (array) $request->input('playerid', []);
        $titolo = trim((string) $request->input('titolo', ''));
        $testo = trim((string) $request->input('testo', ''));
        $link = trim((string) $request->input('link', ''));
        $agid = $request->input('agenziaid');
        $testolink = $link !== '' ? 'Visita Ora!' : null;

        if ($playerids === [] || $titolo === '' || $testo === '') {
            return response()->json(['success' => false, 'message' => 'Campi obbligatori mancanti o nessun destinatario.']);
        }
        if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'URL non valido.']);
        }

        $imageUrl = $this->saveImage($request, 'media', public_path('includes/images'), 'includes/images', 10);
        if (is_array($imageUrl)) {
            return response()->json($imageUrl);
        }

        $users = Cliente::whereIn('playerid', $playerids)->get(['username', 'playerid']);
        if ($users->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Nessun destinatario trovato.']);
        }

        Notifica::create([
            'destinatari' => $users->pluck('username')->implode(','),
            'titolo' => $titolo,
            'contenuto' => $testo,
            'immagine' => $imageUrl ?? '',
            'link' => $link,
            'testolink' => $testolink,
            'dataora' => now()->format('Y-m-d H:i:s'),
            'agenziaid' => $agid,
        ]);

        $error = $this->push($agid, $titolo, $testo, $users->pluck('playerid')->all(), $imageUrl);
        if ($error !== null) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        return response()->json(['success' => true, 'message' => 'Notifiche inviate con successo.']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Push mirato; null se ok (o config mancante, come il legacy private/selected
     * che inviava solo se configurato), messaggio d'errore altrimenti.
     *
     * @param  list<string>  $externalIds
     */
    private function push(mixed $agid, string $titolo, string $testo, array $externalIds, ?string $imageUrl): ?string
    {
        $osConfig = AgenziaNew::find((int) $agid);
        if ($osConfig === null || ! $osConfig->os_app_id || ! $osConfig->os_api_key) {
            return null;
        }

        $payload = $this->oneSignal->buildPayload((string) $osConfig->os_app_id, $titolo, $testo, $externalIds, $imageUrl);
        [$status, $body] = $this->oneSignal->send((string) $osConfig->os_api_key, $payload);

        return $status === 200 ? null : OneSignalService::errorMessage('Errore invio notifica OneSignal.', $body);
    }

    /**
     * Upload immagine notifica. Ritorna: null (nessun file), URL pubblico (ok),
     * o array {success:false,message} (errore di validazione, da ritornare al client).
     *
     * @return string|array<string, mixed>|null
     */
    private function saveImage(Request $request, string $field, string $dir, string $urlPrefix, int $maxMb): string|array|null
    {
        $file = $request->file($field);
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return null;
        }

        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['success' => false, 'message' => 'Formato immagine non valido.'];
        }
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            return ['success' => false, 'message' => "Il file supera il limite di {$maxMb}MB."];
        }

        File::ensureDirectoryExists($dir);
        $fileName = time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $file->move($dir, $fileName);

        return url($urlPrefix.'/'.$fileName);
    }
}
