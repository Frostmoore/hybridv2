<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Models\Notifica;
use App\Models\NotificaGenerale;
use App\Services\OneSignalService;
use Illuminate\Http\Request;

/**
 * Notifiche del pannello admin: broadcast GLOBALE a TUTTI gli utenti del
 * sistema (manutenzione programmata, modifiche privacy, ecc.).
 *
 * Riusa il flusso "invia a tutti" del pannello agenzie, iterandolo su ogni
 * agenzia: per ciascuna salva la notifica in-app (`notifiche`), il banner
 * (`notifiche_generali`, letto dall'app via general.php) e — se l'agenzia ha
 * le credenziali OneSignal — invia il push usando l'app OneSignal di quella
 * agenzia. Vedi critics.md.
 */
class AdminNotificationController extends Controller
{
    /** Durata default del banner generale, in giorni. */
    private const DEFAULT_SCADENZA_DAYS = 30;

    public function __construct(private readonly OneSignalService $oneSignal) {}

    public function page()
    {
        // Le NotificaGenerale dello stesso broadcast hanno titolo+testo+scadenza
        // identici (una per agenzia). Le raggruppo per mostrarle come un solo invio.
        $broadcasts = NotificaGenerale::orderByDesc('id')->get()
            ->groupBy(fn ($n) => $n->notifica_titolo.'|||'.$n->notifica_testo.'|||'.$n->notifica_scadenza)
            ->map(fn ($g) => (object) [
                'id' => $g->first()->id,   // record rappresentativo del gruppo
                'titolo' => $g->first()->notifica_titolo,
                'testo' => $g->first()->notifica_testo,
                'scadenza' => $g->first()->notifica_scadenza,
                'agenzie' => $g->count(),
            ])
            ->values();

        return view('admin.notifiche', ['broadcasts' => $broadcasts]);
    }

    /** Elimina tutte le NotificaGenerale di un broadcast (gruppo). */
    public function deleteBroadcast(Request $request)
    {
        $n = $this->matchQuery($request)->delete();

        return redirect('notifiche')->with('status', "Broadcast eliminato ($n notifiche rimosse).");
    }

    /** Aggiorna la scadenza di tutte le NotificaGenerale di un broadcast. */
    public function updateBroadcastScadenza(Request $request)
    {
        $scadenza = str_replace('T', ' ', trim((string) $request->input('nuova_scadenza', '')));
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $scadenza)) {
            return redirect('notifiche')->with('status', 'Scadenza non valida.');
        }
        if (strlen($scadenza) === 16) {
            $scadenza .= ':00';
        }
        $n = $this->matchQuery($request)->update(['notifica_scadenza' => $scadenza]);

        return redirect('notifiche')->with('status', "Scadenza aggiornata ($n notifiche).");
    }

    /** Query che identifica un broadcast: stesso titolo+testo+scadenza. */
    private function matchQuery(Request $request)
    {
        $ref = NotificaGenerale::find((int) $request->input('ref_id'));
        if ($ref === null) {
            return NotificaGenerale::whereRaw('1 = 0');   // gruppo non trovato
        }
        $q = NotificaGenerale::where('notifica_titolo', $ref->notifica_titolo)
            ->where('notifica_testo', $ref->notifica_testo);

        return $ref->notifica_scadenza === null
            ? $q->whereNull('notifica_scadenza')
            : $q->where('notifica_scadenza', $ref->notifica_scadenza);
    }

    public function broadcast(Request $request)
    {
        $titolo = trim((string) $request->input('notificationTitle', ''));
        $testo = trim((string) $request->input('notificationText', ''));

        if ($titolo === '' || $testo === '') {
            return redirect('notifiche')->with('status', 'Titolo e testo sono obbligatori.');
        }

        // Scadenza del banner generale: dal form (date) o default +30 giorni.
        $scadenzaInput = trim((string) $request->input('notificationExpiry', ''));
        $scadenza = $scadenzaInput !== '' && strtotime($scadenzaInput) !== false
            ? date('Y-m-d H:i:s', (int) strtotime($scadenzaInput))
            : now()->addDays(self::DEFAULT_SCADENZA_DAYS)->format('Y-m-d H:i:s');

        $now = now()->format('Y-m-d H:i:s');
        $agenzie = AgenziaNew::all();

        $pushOk = 0;
        $pushSkip = 0;
        $count = 0;

        foreach ($agenzie as $agenzia) {
            $agid = (int) $agenzia->id;
            $usernames = Cliente::where('agenziaid', $agid)->pluck('username')->all();

            // Notifica in-app (privata) a tutti gli utenti dell'agenzia
            if ($usernames !== []) {
                Notifica::create([
                    'titolo' => $titolo,
                    'contenuto' => $testo,
                    'destinatari' => implode(',', $usernames),
                    'letta_da' => '',
                    'dataora' => $now,
                    'agenziaid' => $agid,
                ]);
            }

            // Banner generale (mostrato dall'app via general.php finché non scade)
            NotificaGenerale::create([
                'notifica_titolo' => $titolo,
                'notifica_testo' => $testo,
                'notifica_scadenza' => $scadenza,
                'notifica_agid' => $agid,
            ]);

            $count++;

            // Push OneSignal sull'app della singola agenzia (se configurata)
            if ($agenzia->os_app_id && $agenzia->os_api_key) {
                $payload = $this->oneSignal->buildPayload((string) $agenzia->os_app_id, $titolo, $testo, null, null);
                [$status] = $this->oneSignal->send((string) $agenzia->os_api_key, $payload);
                $status === 200 ? $pushOk++ : $pushSkip++;
            } else {
                $pushSkip++;
            }
        }

        return redirect('notifiche')->with(
            'status',
            "Broadcast inviato a $count agenzie · push OneSignal: $pushOk ok, $pushSkip senza push (solo in-app)."
        );
    }
}
