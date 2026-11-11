<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Polizza;
use App\Support\SpreadsheetReader;
use RuntimeException;

/**
 * Importa polizze da un foglio (CSV / XLSX / "XLS") con la mappatura delle
 * colonne **scelta a mano** dall'operatore dell'agenzia.
 *
 * Perche' la mappatura e' manuale e non automatica: l'importer storico
 * (`App\Http\Controllers\Web\ImportPolizzeController`) indovina le colonne da
 * una lista di alias, e quando non le indovina l'import muore con "Colonna CF
 * non trovata" senza che l'operatore possa farci niente. Ogni gestionale
 * intesta le colonne a modo suo — "COD_FISC", "Cod.Fiscale", "CF CONTRAENTE" —
 * quindi la lista di alias non puo' essere completa per definizione. Qui gli
 * alias restano, ma solo per **pre-selezionare** le tendine: l'ultima parola e'
 * dell'operatore, che vede il nome della colonna e i primi valori veri e decide.
 *
 * Le date si normalizzano in `YYYY-MM-DD`. Non e' un dettaglio estetico:
 * l'app (`Polizza.formatDateIt` in `assidim`) spezza la data sul `-` e si
 * aspetta anno-mese-giorno, e l'ordinamento della lista polizze e' un confronto
 * fra stringhe, che da' l'ordine giusto solo in formato ISO. L'importer legacy
 * salvava `dd/mm/yyyy`: e' una trappola che qui non si ripete.
 *
 * @see SsfImportService per l'import degli export SSF, dove i
 *      nomi delle colonne sono fissi e la mappatura non serve.
 */
final class PolizzeSheetImportService
{
    /**
     * I campi della tabella `polizze` che si possono popolare da un foglio, con
     * l'etichetta che l'operatore legge nella tendina.
     *
     * L'ordine e' quello in cui compaiono nella tendina: prima i due
     * obbligatori, poi il resto in ordine di utilita'.
     *
     * @var array<string, string>
     */
    public const CAMPI = [
        'cf' => 'Codice fiscale del cliente',
        'n_polizza' => 'Numero di polizza',
        'contraente' => 'Contraente (nome e cognome)',
        'compagnia' => 'Compagnia',
        'ramo' => 'Ramo',
        'prodotto' => 'Prodotto',
        'targa' => 'Targa',
        'frazionamento' => 'Frazionamento',
        'data_decorrenza' => 'Data di decorrenza',
        'data_scadenza_titolo' => 'Prossima scadenza (scadenza del titolo)',
        'data_scadenza_contratto' => 'Scadenza del contratto',
        'stato_polizza' => 'Stato della polizza',
    ];

    /**
     * Campi senza i quali la riga non si puo' salvare.
     *
     * `cf` e' il solo appiglio fra la polizza e l'utente dell'app: senza, la
     * polizza esiste in tabella e non la vede nessuno. `n_polizza` e' metа'
     * della chiave univoca `(n_polizza, id_agenzia)`: senza, due righe diverse
     * si sovrascriverebbero a vicenda.
     *
     * @var list<string>
     */
    public const OBBLIGATORI = ['cf', 'n_polizza'];

    /** @var list<string> */
    private const CAMPI_DATA = ['data_decorrenza', 'data_scadenza_titolo', 'data_scadenza_contratto'];

    /**
     * Alias degli header, usati **solo** per pre-selezionare le tendine.
     * Confronto fatto sul nome ridotto a sole lettere e cifre minuscole.
     *
     * @var array<string, list<string>>
     */
    private const ALIAS = [
        'cf' => ['cf', 'codicefiscale', 'codfiscale', 'codfisc', 'fiscale', 'cfcontraente', 'codfiscalecontraente', 'codicefiscalecliente'],
        'contraente' => ['contraente', 'nominativo', 'nomecognome', 'intestatario', 'cliente', 'denominazione', 'ragionesociale'],
        'n_polizza' => ['npolizza', 'numeropolizza', 'polizza', 'numpolizza', 'nrpolizza', 'nropolizza', 'numero', 'numeropolizzacmp'],
        'compagnia' => ['compagnia', 'impresa', 'assicurazione', 'compagniaania'],
        'ramo' => ['ramo', 'ramocmp', 'ramoshare'],
        'prodotto' => ['prodotto', 'tipoprodotto', 'prodottocmp', 'descrizioneprodotto'],
        'targa' => ['targa', 'teo', 'telaio', 'targaveicolo'],
        'frazionamento' => ['frazionamento', 'fraz', 'frazionamentoshare', 'rateazione'],
        'data_decorrenza' => ['datadecorrenza', 'decorrenza', 'dataeffetto', 'effetto', 'datainizio', 'inizio', 'dataizio'],
        'data_scadenza_titolo' => ['datascadenzatitolo', 'scadenzatitolo', 'scadtitolo', 'scadenzaincassato', 'prossimascadenza', 'scadenzarata'],
        'data_scadenza_contratto' => ['datascadenzacontratto', 'scadenzacontratto', 'scadcontratto', 'scadenza', 'scadenzaeffettiva'],
        'stato_polizza' => ['statopolizza', 'stato', 'codstatoshare', 'statocontratto'],
    ];

    /** Lunghezze della tabella `polizze`: si tronca qui, non si lascia troncare al DB. */
    private const LIMITI = [
        'contraente' => 255, 'n_polizza' => 100, 'compagnia' => 255, 'ramo' => 255,
        'prodotto' => 255, 'targa' => 30, 'frazionamento' => 50, 'stato_polizza' => 100,
        'cf' => 16,
    ];

    private const LOTTO = 500;

    /** Estensioni che la pagina di import accetta. */
    public const ESTENSIONI = ['csv', 'txt', 'xlsx', 'xls'];

    // ─── lettura del file ────────────────────────────────────────────────────

    /**
     * Legge il foglio e restituisce le righe grezze, senza interpretarle.
     *
     * Il tipo si riconosce dal **contenuto**, non dall'estensione, perche' i
     * gestionali esportano abitualmente file chiamati `.xls` che non sono
     * cartelle Excel: a volte sono CSV, a volte tabelle HTML. Fidarsi del nome
     * vorrebbe dire rifiutare file perfettamente leggibili.
     *
     * @return list<list<string>>
     *
     * @throws RuntimeException con un messaggio mostrabile all'operatore
     */
    public static function leggi(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Il file non si riesce ad aprire.');
        }
        $testa = (string) fread($handle, 2048);
        fclose($handle);

        // Cartella Excel moderna: e' uno ZIP, inizia per "PK".
        if (str_starts_with($testa, 'PK')) {
            $righe = SpreadsheetReader::xlsx($path);
            if (is_string($righe)) {
                throw new RuntimeException($righe);
            }

            return self::senzaRigheVuote($righe);
        }

        // Excel 97-2003 vero (contenitore OLE2): non lo sappiamo leggere.
        if (str_starts_with($testa, "\xD0\xCF\x11\xE0")) {
            throw new RuntimeException(
                'Questo e\' un file Excel in formato vecchio (.xls 97-2003), che non '
                .'riusciamo a leggere. Aprilo con Excel e salvalo come "Cartella di '
                .'lavoro Excel (.xlsx)" oppure come "CSV", poi ricaricalo.'
            );
        }

        // "Finto xls": in realta' una tabella HTML. Lo esportano cosi' diversi gestionali.
        if (preg_match('/<\s*(table|html)\b/i', $testa) === 1) {
            return self::senzaRigheVuote(self::tabellaHtml($path));
        }

        $sep = SpreadsheetReader::detectCsvSeparator($path);
        $righe = SpreadsheetReader::csv($path, $sep);
        if ($righe === []) {
            throw new RuntimeException('Il file risulta vuoto.');
        }

        return self::senzaRigheVuote($righe);
    }

    /**
     * Legge la prima tabella di un documento HTML.
     *
     * @return list<list<string>>
     */
    private static function tabellaHtml(string $path): array
    {
        $html = (string) file_get_contents($path);
        $doc = new \DOMDocument;
        $precedente = libxml_use_internal_errors(true);
        // L'HTML sputato dai gestionali e' spesso malformato: gli errori si
        // ignorano di proposito, a loadHTML basta recuperare il recuperabile.
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);

        $righe = [];
        foreach ($doc->getElementsByTagName('tr') as $tr) {
            $riga = [];
            foreach ($tr->childNodes as $cella) {
                if ($cella instanceof \DOMElement && in_array(strtolower($cella->tagName), ['td', 'th'], true)) {
                    $riga[] = trim(preg_replace('/\s+/u', ' ', (string) $cella->textContent) ?? '');
                }
            }
            if ($riga !== []) {
                $righe[] = $riga;
            }
        }

        if ($righe === []) {
            throw new RuntimeException('Nel file non si trova nessuna tabella leggibile.');
        }

        return $righe;
    }

    /**
     * @param  list<list<string>>  $righe
     * @return list<list<string>>
     */
    private static function senzaRigheVuote(array $righe): array
    {
        $pulite = array_values(array_filter(
            $righe,
            fn (array $r): bool => implode('', array_map('trim', $r)) !== '',
        ));

        if ($pulite === []) {
            throw new RuntimeException('Il file non contiene righe con dei valori.');
        }

        return $pulite;
    }

    // ─── mappatura ───────────────────────────────────────────────────────────

    /**
     * Pre-seleziona le tendine confrontando gli header con gli alias noti.
     *
     * Un campo si assegna una volta sola: se due colonne si chiamano entrambe
     * "Scadenza" vince la prima, e l'operatore sistema a mano.
     *
     * @param  list<string>  $intestazioni
     * @return array<int, string> indice colonna => campo
     */
    public static function suggerisciMappatura(array $intestazioni): array
    {
        $suggerita = [];
        $presi = [];

        foreach ($intestazioni as $indice => $nome) {
            $norm = preg_replace('/[^a-z0-9]/', '', strtolower(trim($nome))) ?? '';
            if ($norm === '') {
                continue;
            }
            foreach (self::ALIAS as $campo => $alias) {
                if (! isset($presi[$campo]) && in_array($norm, $alias, true)) {
                    $suggerita[$indice] = $campo;
                    $presi[$campo] = true;
                    break;
                }
            }
        }

        return $suggerita;
    }

    /**
     * Nome di comodo per una colonna senza intestazione: "Colonna A", "B", ...
     */
    public static function nomeColonna(int $indice): string
    {
        $nome = '';
        $n = $indice;
        do {
            $nome = chr(65 + ($n % 26)).$nome;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return 'Colonna '.$nome;
    }

    /**
     * Controlla la mappatura scelta dall'operatore.
     *
     * @param  array<int, string>  $mappatura  indice colonna => campo
     * @return list<string> gli errori, vuoto se va bene
     */
    public static function erroriMappatura(array $mappatura): array
    {
        $errori = [];
        $campi = array_values($mappatura);

        foreach (self::OBBLIGATORI as $campo) {
            if (! in_array($campo, $campi, true)) {
                $errori[] = 'Manca la colonna da usare come "'.self::CAMPI[$campo].'": e\' obbligatoria.';
            }
        }

        $doppi = array_keys(array_filter(array_count_values($campi), fn (int $n): bool => $n > 1));
        foreach ($doppi as $campo) {
            $errori[] = 'Hai assegnato "'.(self::CAMPI[$campo] ?? $campo).'" a piu\' di una colonna: scegline una sola.';
        }

        foreach ($campi as $campo) {
            if (! isset(self::CAMPI[$campo])) {
                $errori[] = 'Campo sconosciuto: '.$campo;
            }
        }

        return $errori;
    }

    // ─── import ──────────────────────────────────────────────────────────────

    /**
     * Scrive le righe nella tabella `polizze` per **una sola** agenzia.
     *
     * `$agenziaId` non arriva mai da un campo del form: lo mette il controller
     * prendendolo dall'operatore in sessione. E' l'unica garanzia che un'agenzia
     * non possa scrivere nelle polizze di un'altra.
     *
     * @param  list<list<string>>  $righe  tutte le righe, intestazione compresa se c'e'
     * @param  array<int, string>  $mappatura  indice colonna => campo
     * @return array<string, mixed> resoconto: quante importate e, soprattutto,
     *                              quante scartate e per quale motivo
     *
     * @throws RuntimeException se la mappatura non e' valida
     */
    public function importa(
        array $righe,
        array $mappatura,
        int $agenziaId,
        bool $sostituisci = false,
        bool $primaRigaIntestazione = true,
    ): array {
        $errori = self::erroriMappatura($mappatura);
        if ($errori !== []) {
            throw new RuntimeException(implode(' ', $errori));
        }
        if ($agenziaId <= 0) {
            throw new RuntimeException('Agenzia non identificata.');
        }

        if ($primaRigaIntestazione) {
            array_shift($righe);
        }

        $conteggi = [
            'lette' => 0, 'importate' => 0,
            'scartate_senza_cf' => 0, 'scartate_senza_numero' => 0,
            'cf_non_standard' => 0, 'date_non_riconosciute' => 0, 'valori_troncati' => 0,
            'sostituite' => $sostituisci,
        ];

        // Si cancella solo DOPO aver validato la mappatura: altrimenti una
        // mappatura sbagliata svuoterebbe l'archivio dell'agenzia senza
        // rimpiazzarlo con niente.
        if ($sostituisci) {
            Polizza::where('id_agenzia', $agenziaId)->delete();
        }

        $campoPerIndice = $mappatura;
        $lotto = [];

        foreach ($righe as $riga) {
            $conteggi['lette']++;

            $valori = [];
            foreach ($campoPerIndice as $indice => $campo) {
                $valori[$campo] = trim((string) ($riga[$indice] ?? ''));
            }

            $cf = strtoupper(preg_replace('/\s+/', '', $valori['cf'] ?? '') ?? '');
            $numero = $valori['n_polizza'] ?? '';

            if ($cf === '') {
                $conteggi['scartate_senza_cf']++;

                continue;
            }
            if ($numero === '') {
                $conteggi['scartate_senza_numero']++;

                continue;
            }
            if (preg_match('/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/', $cf) !== 1) {
                // Si importa comunque, ma si conta: un codice che non ha la
                // forma del codice fiscale italiano (tipicamente una partita
                // IVA) non corrispondera' a nessun utente dell'app, e
                // l'operatore deve saperlo invece di chiedersi perche' il
                // cliente non vede niente.
                $conteggi['cf_non_standard']++;
            }

            $record = ['id_agenzia' => $agenziaId, 'cf' => $this->tronca($cf, 'cf')];
            foreach (array_keys(self::CAMPI) as $campo) {
                if ($campo === 'cf') {
                    continue;
                }
                $valore = $valori[$campo] ?? '';
                if (in_array($campo, self::CAMPI_DATA, true)) {
                    $valore = $this->data($valore, $conteggi['date_non_riconosciute']);
                }
                $prima = $valore;
                $valore = $this->tronca($valore, $campo);
                if ($valore !== $prima) {
                    $conteggi['valori_troncati']++;
                }
                $record[$campo] = $valore;
            }

            $lotto[] = $record;
            $conteggi['importate']++;

            if (count($lotto) >= self::LOTTO) {
                $this->salva($lotto);
                $lotto = [];
            }
        }

        if ($lotto !== []) {
            $this->salva($lotto);
        }

        return $conteggi;
    }

    /** @param  list<array<string, mixed>>  $lotto */
    private function salva(array $lotto): void
    {
        // Stessa chiave e stesse colonne aggiornate dell'import SSF e di quello
        // legacy: ricaricare un file sovrascrive le polizze, non le duplica.
        Polizza::upsert(
            $lotto,
            ['n_polizza', 'id_agenzia'],
            ['cf', 'contraente', 'compagnia', 'ramo', 'prodotto', 'targa', 'frazionamento',
                'data_decorrenza', 'data_scadenza_titolo', 'data_scadenza_contratto', 'stato_polizza'],
        );
    }

    // ─── conversioni ─────────────────────────────────────────────────────────

    /**
     * Qualunque data leggibile → `YYYY-MM-DD`.
     *
     * Riconosce: ISO (con o senza ora), `dd/mm/yyyy` e varianti con `-` o `.`,
     * l'anno a due cifre, e il seriale Excel (le celle data di un .xlsx
     * arrivano come numero di giorni dal 1900, non come testo).
     *
     * Quello che non riconosce lo restituisce intatto e incrementa il
     * contatore: meglio un valore strano in tabella, visibile nel resoconto,
     * che una data inventata.
     */
    private function data(string $valore, int &$nonRiconosciute): string
    {
        $valore = trim($valore);
        if ($valore === '') {
            return '';
        }

        // Seriale Excel. Il 25569 e' lo scarto fra l'epoca Excel e quella Unix;
        // la finestra 20000-80000 copre dal 1954 al 2089 e tiene fuori i numeri
        // che non sono date.
        if (is_numeric($valore)) {
            $n = (float) $valore;
            if ($n > 20000 && $n < 80000) {
                return date('Y-m-d', (int) (($n - 25569) * 86400));
            }
            $nonRiconosciute++;

            return $valore;
        }

        // Via l'eventuale parte orario.
        $soloData = preg_split('/[ T]/', $valore)[0] ?? $valore;

        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $soloData, $m) === 1) {
            [$anno, $mese, $giorno] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2}|\d{4})$/', $soloData, $m) === 1) {
            [$giorno, $mese, $anno] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($anno < 100) {
                // Due cifre: sotto 70 e' questo secolo, sopra il precedente.
                $anno += $anno < 70 ? 2000 : 1900;
            }
        } else {
            $nonRiconosciute++;

            return $valore;
        }

        if (! checkdate($mese, $giorno, $anno)) {
            $nonRiconosciute++;

            return $valore;
        }

        return sprintf('%04d-%02d-%02d', $anno, $mese, $giorno);
    }

    private function tronca(string $valore, string $campo): string
    {
        $limite = self::LIMITI[$campo] ?? null;

        return $limite === null ? $valore : mb_substr($valore, 0, $limite);
    }
}
