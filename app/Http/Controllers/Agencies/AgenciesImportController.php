<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agencies;

use App\Http\Controllers\Controller;
use App\Models\Operatore;
use App\Models\Polizza;
use App\Services\PolizzeSheetImportService;
use App\Services\SsfImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Import delle polizze dentro il pannello agenzie.
 *
 * Finora le polizze si caricavano **solo** dal dominio principale, con una
 * pagina protetta da una password condivisa e una tendina per scegliere
 * l'agenzia: in pratica le caricava l'amministratore per conto di tutti. Qui
 * le carica l'agenzia stessa, e l'agenzia di destinazione **non e' un campo del
 * form**: viene dall'operatore in sessione (`$operatore->agid`). E' questo che
 * rende la pagina apribile alle agenzie senza che una possa scrivere nelle
 * polizze di un'altra, anche manomettendo la richiesta.
 *
 * Due strade, perche' i file che arrivano sono di due nature diverse:
 *
 * 1. **ZIP dell'export SSF** — struttura fissa e nota (`polizze.csv`,
 *    `clienti.csv`, ...), colonne con nomi stabili. Non c'e' niente da
 *    mappare: lo legge {@see SsfImportService}, che applica anche le regole di
 *    selezione (solo attive, via i segnaposto, solo persone fisiche).
 * 2. **Foglio CSV / XLSX / "XLS"** — una tabella qualunque. Qui la mappatura e'
 *    manuale e in due passi: si carica, si vede la tabella colonna per colonna
 *    con i primi valori veri, si dice a ogni colonna che campo e', e solo
 *    allora si importa.
 *
 * Fra i due passi il file resta su disco in `storage/app/private/
 * import-polizze/`, e in sessione ci sta solo un token con le intestazioni e
 * l'anteprima. Le righe non si tengono in sessione di proposito: un export vero
 * ne ha decine di migliaia e la sessione non e' il posto dove parcheggiarle.
 */
class AgenciesImportController extends Controller
{
    /** Chiave di sessione del file in attesa di mappatura. */
    private const SESSIONE = 'import_polizze_in_corso';

    /** Cartella dei file in attesa, sotto `storage/app/private`. */
    private const CARTELLA = 'import-polizze';

    /** Quante righe si mostrano come esempio sotto ogni colonna. */
    private const ANTEPRIMA = 3;

    public function __construct(
        private readonly PolizzeSheetImportService $fogli,
        private readonly SsfImportService $ssf,
    ) {}

    // ─── pagina di partenza ──────────────────────────────────────────────────

    public function page()
    {
        $operatore = $this->operatore();
        $agid = (int) $operatore->agid;

        return view('agencies.polizze_import', [
            'operatore' => $operatore,
            'inArchivio' => Polizza::where('id_agenzia', $agid)->count(),
            'ultimoAggiornamento' => Polizza::where('id_agenzia', $agid)->max('updated_at'),
            'estensioni' => PolizzeSheetImportService::ESTENSIONI,
        ]);
    }

    // ─── passo 1: caricamento ────────────────────────────────────────────────

    /**
     * Riceve il file. Uno ZIP si importa subito; un foglio va al passo della
     * mappatura.
     */
    public function carica(Request $request)
    {
        $operatore = $this->operatore();
        $agid = (int) $operatore->agid;

        $request->validate([
            // 262144 KB = 256 MB. Il limite vero lo impone comunque php.ini
            // (upload_max_filesize / post_max_size): questo serve a dare un
            // errore in italiano invece di una pagina bianca.
            'file' => ['required', 'file', 'max:262144'],
            'modalita' => ['nullable', 'in:aggiorna,sostituisci'],
        ], [
            'file.required' => 'Scegli un file da caricare.',
            'file.max' => 'Il file supera i 256 MB.',
        ]);

        $file = $request->file('file');
        $sostituisci = $request->input('modalita') === 'sostituisci';
        $estensione = strtolower((string) $file->getClientOriginalExtension());

        if ($estensione === 'zip') {
            return $this->importaSsf($file->getRealPath(), $agid, $sostituisci, (string) $file->getClientOriginalName());
        }

        if (! in_array($estensione, PolizzeSheetImportService::ESTENSIONI, true)) {
            return back()->with('errore', 'Formato non gestito: ".'.$estensione.'". '
                .'Carica lo ZIP dell\'export SSF oppure un file '
                .strtoupper(implode(', ', PolizzeSheetImportService::ESTENSIONI)).'.');
        }

        // Si legge subito, prima di parcheggiare il file: se e' illeggibile
        // l'operatore lo scopre ora e non dopo aver compilato la mappatura.
        try {
            $righe = PolizzeSheetImportService::leggi($file->getRealPath());
        } catch (RuntimeException $e) {
            return back()->with('errore', $e->getMessage());
        }

        $this->buttaIlFileInAttesa();

        $token = (string) Str::uuid();
        $percorso = self::CARTELLA.'/'.$token.'.dat';
        $file->storeAs(self::CARTELLA, $token.'.dat', 'local');

        $prima = $righe[0] ?? [];
        $colonne = max(array_map('count', array_slice($righe, 0, 20)));

        $request->session()->put(self::SESSIONE, [
            'token' => $token,
            'percorso' => $percorso,
            'agid' => $agid,
            'nome' => (string) $file->getClientOriginalName(),
            'righe_totali' => count($righe),
            'colonne' => $colonne,
            'intestazioni' => array_map(fn ($i) => (string) ($prima[$i] ?? ''), range(0, $colonne - 1)),
            'anteprima' => array_map(
                fn (array $r): array => array_map(fn ($i) => (string) ($r[$i] ?? ''), range(0, $colonne - 1)),
                array_slice($righe, 1, self::ANTEPRIMA),
            ),
            'sostituisci' => $sostituisci,
        ]);

        return redirect('polizze/mappatura');
    }

    // ─── passo 2: mappatura ──────────────────────────────────────────────────

    public function mappatura(Request $request)
    {
        $attesa = $this->fileInAttesa($request);
        if ($attesa === null) {
            return redirect('polizze')->with('errore', 'Il file da mappare non c\'e\' piu\': ricaricalo.');
        }

        return view('agencies.polizze_mappatura', [
            'operatore' => $this->operatore(),
            'attesa' => $attesa,
            'campi' => PolizzeSheetImportService::CAMPI,
            'obbligatori' => PolizzeSheetImportService::OBBLIGATORI,
            'suggerita' => PolizzeSheetImportService::suggerisciMappatura($attesa['intestazioni']),
        ]);
    }

    /** Esegue l'import col mapping scelto. */
    public function importa(Request $request)
    {
        $attesa = $this->fileInAttesa($request);
        if ($attesa === null) {
            return redirect('polizze')->with('errore', 'Il file da mappare non c\'e\' piu\': ricaricalo.');
        }

        /** @var array<int|string, string> $scelte */
        $scelte = (array) $request->input('colonna', []);
        $mappatura = [];
        foreach ($scelte as $indice => $campo) {
            $campo = (string) $campo;
            if ($campo !== '' && isset(PolizzeSheetImportService::CAMPI[$campo])) {
                $mappatura[(int) $indice] = $campo;
            }
        }

        $errori = PolizzeSheetImportService::erroriMappatura($mappatura);
        if ($errori !== []) {
            return back()->with('errore', implode(' ', $errori))->withInput();
        }

        $percorso = storage_path('app/private/'.$attesa['percorso']);
        if (! is_file($percorso)) {
            $request->session()->forget(self::SESSIONE);

            return redirect('polizze')->with('errore', 'Il file caricato non c\'e\' piu\' sul server: ricaricalo.');
        }

        set_time_limit(0);

        try {
            $righe = PolizzeSheetImportService::leggi($percorso);
            $resoconto = $this->fogli->importa(
                righe: $righe,
                mappatura: $mappatura,
                agenziaId: (int) $attesa['agid'],
                sostituisci: (bool) $attesa['sostituisci'],
                primaRigaIntestazione: $request->boolean('intestazione'),
            );
        } catch (RuntimeException $e) {
            return back()->with('errore', $e->getMessage())->withInput();
        } catch (Throwable $e) {
            report($e);

            return back()->with('errore', 'Import non riuscito: '.$e->getMessage())->withInput();
        }

        $nome = (string) $attesa['nome'];
        $this->buttaIlFileInAttesa();

        return redirect('polizze')->with('esito', [
            'tipo' => 'foglio',
            'nome' => $nome,
            'mappatura' => $mappatura,
            'conteggi' => $resoconto,
        ]);
    }

    /** Rinuncia al file caricato senza importarlo. */
    public function annulla()
    {
        $this->buttaIlFileInAttesa();

        return redirect('polizze')->with('errore', 'Import annullato: il file caricato e\' stato cancellato.');
    }

    // ─── SSF ─────────────────────────────────────────────────────────────────

    private function importaSsf(string $percorso, int $agid, bool $sostituisci, string $nome)
    {
        set_time_limit(0);

        try {
            $resoconto = $this->ssf->importa($percorso, $agid, $sostituisci);
        } catch (RuntimeException $e) {
            return back()->with('errore',
                'Questo ZIP non sembra un export SSF. '.$e->getMessage()
                .' Se e\' un altro tipo di archivio, estrai il CSV e carica quello: '
                .'potrai mappare le colonne a mano.');
        } catch (Throwable $e) {
            report($e);

            return back()->with('errore', 'Import SSF non riuscito: '.$e->getMessage());
        }

        return redirect('polizze')->with('esito', [
            'tipo' => 'ssf',
            'nome' => $nome,
            'conteggi' => $resoconto,
        ]);
    }

    // ─── utilita' ────────────────────────────────────────────────────────────

    private function operatore(): Operatore
    {
        /** @var Operatore $operatore */
        $operatore = Auth::guard('operatore')->user();

        return $operatore;
    }

    /**
     * Il file in attesa di mappatura, o null se non c'e' o non e' di questa
     * agenzia.
     *
     * Il controllo sull'agenzia non e' pedanteria: la sessione sopravvive al
     * cambio di operatore (logout e login di un collega di un'altra agenzia
     * nello stesso browser), e senza questo controllo il secondo si troverebbe
     * davanti il file del primo e lo importerebbe nel proprio archivio.
     *
     * @return array<string, mixed>|null
     */
    private function fileInAttesa(Request $request): ?array
    {
        $attesa = $request->session()->get(self::SESSIONE);
        if (! is_array($attesa) || ! isset($attesa['percorso'], $attesa['agid'])) {
            return null;
        }
        if ((int) $attesa['agid'] !== (int) $this->operatore()->agid) {
            return null;
        }

        return $attesa;
    }

    /**
     * Cancella il file parcheggiato e dimentica la sessione.
     *
     * Si chiama sia a import concluso sia prima di caricarne un altro: i
     * fogli delle agenzie contengono codici fiscali e nominativi veri, e non
     * devono restare su disco oltre il tempo che serve.
     */
    private function buttaIlFileInAttesa(): void
    {
        $attesa = session(self::SESSIONE);
        if (is_array($attesa) && isset($attesa['percorso'])) {
            $percorso = storage_path('app/private/'.$attesa['percorso']);
            if (is_file($percorso)) {
                @unlink($percorso);
            }
        }
        session()->forget(self::SESSIONE);
    }
}
