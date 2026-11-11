<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Services\PolizzeSheetImportService;
use App\Services\SsfImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Il giro di import delle polizze, condiviso fra i due pannelli che lo montano.
 *
 * Sta in un trait e non in una classe base perche' i due controller vivono in
 * mondi diversi — uno nel pannello agenzie, uno in quello admin — e l'unica
 * cosa che hanno in comune e' questa procedura. Quello che cambia fra i due e'
 * raccolto in pochi metodi che la classe che lo usa deve dichiarare:
 *
 * - {@see agenziaDestinazione()} — da dove esce l'id dell'agenzia. Nel pannello
 *   agenzie dalla sessione dell'operatore, in quello admin da una tendina. E'
 *   la differenza che conta: in un caso l'agenzia **non si puo' scegliere**, e
 *   quella e' la ragione per cui la pagina si puo' aprire alle agenzie.
 * - {@see rottaBase()} — il prefisso degli URL (`polizze`, `importa-polizze`).
 * - {@see layoutPagina()} — quale layout Blade avvolge le viste.
 * - {@see chiaveSessione()} — dove si parcheggia il file fra i due passi. Le due
 *   chiavi sono diverse di proposito: i due pannelli possono essere aperti
 *   nello stesso browser e non devono rubarsi il file a vicenda.
 *
 * Il giro in se':
 *
 * 1. **carica** — arriva un file. Uno ZIP SSF si importa subito (colonne a nomi
 *    fissi, le regole stanno in {@see SsfImportService}); un foglio si legge
 *    per controllare che sia leggibile, si parcheggia su disco e si passa al
 *    secondo passo.
 * 2. **mappatura** — si mostrano le colonne col nome vero e i primi valori, e
 *    si sceglie a mano a che campo corrisponde ognuna.
 * 3. **importa** — si valida la mappatura, si rilegge il file e si scrive.
 *
 * Il file sta in `storage/app/private/import-polizze/` solo fra il passo 1 e il
 * 3, e viene buttato appena l'import finisce o si annulla: contiene codici
 * fiscali e nominativi veri e non deve restare su disco oltre il necessario.
 * Le righe non si tengono in sessione di proposito: un export vero ne ha
 * decine di migliaia e la sessione non e' il posto dove parcheggiarle.
 */
trait ImportaLePolizze
{
    /** Cartella dei file in attesa, sotto `storage/app/private`. */
    private static string $cartella = 'import-polizze';

    /** Quante righe si mostrano come esempio sotto ogni colonna. */
    private static int $anteprima = 3;

    // ─── quello che i due pannelli dichiarano a modo loro ────────────────────

    /**
     * Id dell'agenzia in cui scrivere, o 0 se non e' stata scelta.
     *
     * @param  bool  $nuovo  true al passo 1 (il valore arriva dalla richiesta),
     *                       false ai passi successivi (si usa quello salvato).
     */
    abstract protected function agenziaDestinazione(Request $request, bool $nuovo): int;

    abstract protected function rottaBase(): string;

    abstract protected function layoutPagina(): string;

    abstract protected function chiaveSessione(): string;

    /**
     * Dati in piu' per la vista (la tendina delle agenzie nel pannello admin).
     *
     * @return array<string, mixed>
     */
    protected function datiVista(): array
    {
        return [];
    }

    /** Nome dell'agenzia scelta, da mostrare nel riepilogo. Null = non serve. */
    protected function nomeAgenzia(int $id): ?string
    {
        return null;
    }

    // ─── passo 1 ─────────────────────────────────────────────────────────────

    /** Riceve il file: uno ZIP si importa subito, un foglio va alla mappatura. */
    public function carica(Request $request)
    {
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

        $agenziaId = $this->agenziaDestinazione($request, true);
        if ($agenziaId <= 0) {
            return back()->with('errore', 'Scegli l\'agenzia in cui importare le polizze.')->withInput();
        }

        $file = $request->file('file');
        $sostituisci = $request->input('modalita') === 'sostituisci';
        $estensione = strtolower((string) $file->getClientOriginalExtension());

        if ($estensione === 'zip') {
            return $this->importaSsf($file->getRealPath(), $agenziaId, $sostituisci, (string) $file->getClientOriginalName());
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
        $file->storeAs(self::$cartella, $token.'.dat', 'local');

        $prima = $righe[0] ?? [];
        $colonne = max(array_map('count', array_slice($righe, 0, 20)));

        $request->session()->put($this->chiaveSessione(), [
            'token' => $token,
            'percorso' => self::$cartella.'/'.$token.'.dat',
            'agid' => $agenziaId,
            'agenzia' => $this->nomeAgenzia($agenziaId),
            'nome' => (string) $file->getClientOriginalName(),
            'righe_totali' => count($righe),
            'colonne' => $colonne,
            'intestazioni' => array_map(fn ($i) => (string) ($prima[$i] ?? ''), range(0, $colonne - 1)),
            'anteprima' => array_map(
                fn (array $r): array => array_map(fn ($i) => (string) ($r[$i] ?? ''), range(0, $colonne - 1)),
                array_slice($righe, 1, self::$anteprima),
            ),
            'sostituisci' => $sostituisci,
        ]);

        return redirect($this->rottaBase().'/mappatura');
    }

    // ─── passo 2 ─────────────────────────────────────────────────────────────

    public function mappatura(Request $request)
    {
        $attesa = $this->fileInAttesa($request);
        if ($attesa === null) {
            return redirect($this->rottaBase())->with('errore', 'Il file da mappare non c\'e\' piu\': ricaricalo.');
        }

        return view('polizze.mappatura', array_merge($this->datiVista(), [
            'layout' => $this->layoutPagina(),
            'base' => $this->rottaBase(),
            'attesa' => $attesa,
            'campi' => PolizzeSheetImportService::CAMPI,
            'obbligatori' => PolizzeSheetImportService::OBBLIGATORI,
            'suggerita' => PolizzeSheetImportService::suggerisciMappatura($attesa['intestazioni']),
        ]));
    }

    /** Esegue l'import con la mappatura scelta. */
    public function importa(Request $request)
    {
        $attesa = $this->fileInAttesa($request);
        if ($attesa === null) {
            return redirect($this->rottaBase())->with('errore', 'Il file da mappare non c\'e\' piu\': ricaricalo.');
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
            $request->session()->forget($this->chiaveSessione());

            return redirect($this->rottaBase())->with('errore', 'Il file caricato non c\'e\' piu\' sul server: ricaricalo.');
        }

        set_time_limit(0);

        try {
            $righe = PolizzeSheetImportService::leggi($percorso);
            $resoconto = app(PolizzeSheetImportService::class)->importa(
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
        $agenzia = $attesa['agenzia'] ?? null;
        $this->buttaIlFileInAttesa();

        return redirect($this->rottaBase())->with('esito', [
            'tipo' => 'foglio',
            'nome' => $nome,
            'agenzia' => $agenzia,
            'mappatura' => $mappatura,
            'conteggi' => $resoconto,
        ]);
    }

    /** Rinuncia al file caricato senza importarlo. */
    public function annulla()
    {
        $this->buttaIlFileInAttesa();

        return redirect($this->rottaBase())->with('errore', 'Import annullato: il file caricato e\' stato cancellato.');
    }

    // ─── SSF ─────────────────────────────────────────────────────────────────

    private function importaSsf(string $percorso, int $agenziaId, bool $sostituisci, string $nome)
    {
        set_time_limit(0);

        try {
            $resoconto = app(SsfImportService::class)->importa($percorso, $agenziaId, $sostituisci);
        } catch (RuntimeException $e) {
            return back()->with('errore',
                'Questo ZIP non sembra un export SSF. '.$e->getMessage()
                .' Se e\' un altro tipo di archivio, estrai il CSV e carica quello: '
                .'potrai mappare le colonne a mano.');
        } catch (Throwable $e) {
            report($e);

            return back()->with('errore', 'Import SSF non riuscito: '.$e->getMessage());
        }

        return redirect($this->rottaBase())->with('esito', [
            'tipo' => 'ssf',
            'nome' => $nome,
            'agenzia' => $this->nomeAgenzia($agenziaId),
            'conteggi' => $resoconto,
        ]);
    }

    // ─── utilita' ────────────────────────────────────────────────────────────

    /**
     * Il file in attesa di mappatura, o null se non c'e' o non e' di chi lo
     * sta chiedendo.
     *
     * Il controllo sull'agenzia non e' pedanteria: la sessione sopravvive al
     * cambio di utente (logout e login di un collega nello stesso browser), e
     * senza questo controllo il secondo si troverebbe davanti il file del primo.
     * Nel pannello admin l'agenzia e' scelta a mano e il confronto e' con
     * quella salvata, quindi passa sempre: li' il controllo vero e' la sessione
     * di amministratore che protegge la rotta.
     *
     * @return array<string, mixed>|null
     */
    private function fileInAttesa(Request $request): ?array
    {
        $attesa = $request->session()->get($this->chiaveSessione());
        if (! is_array($attesa) || ! isset($attesa['percorso'], $attesa['agid'])) {
            return null;
        }
        if ((int) $attesa['agid'] !== $this->agenziaDestinazione($request, false)) {
            return null;
        }

        return $attesa;
    }

    /** Cancella il file parcheggiato e dimentica la sessione. */
    private function buttaIlFileInAttesa(): void
    {
        $attesa = session($this->chiaveSessione());
        if (is_array($attesa) && isset($attesa['percorso'])) {
            $percorso = storage_path('app/private/'.$attesa['percorso']);
            if (is_file($percorso)) {
                @unlink($percorso);
            }
        }
        session()->forget($this->chiaveSessione());
    }
}
