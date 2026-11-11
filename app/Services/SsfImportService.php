<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Polizza;
use RuntimeException;
use ZipArchive;

/**
 * Importa un export SSF (ZIP di CSV) nella tabella `polizze`.
 *
 * L'export e' quello che le agenzie tirano fuori dal loro gestionale. Oggi lo
 * caricano loro; in prospettiva lo scaricheremo noi con le loro credenziali, ma
 * il formato sara' lo stesso e questo servizio non cambia.
 *
 * Le regole di selezione non sono arbitrarie: sono state decise guardando un
 * export vero da 69.504 polizze, e stanno scritte in `memory/decisioni.md` con
 * i numeri che le hanno motivate. In sintesi:
 *
 * 1. **Solo le polizze attive** (`COD_STATO_SHARE = 'AT'`). Tre quarti
 *    dell'export sono stornate: importarle vorrebbe dire mostrare al cliente
 *    polizze morte come se fossero in corso.
 * 2. **Via le righe il cui numero di polizza compare su piu' clienti.** Sono
 *    segnaposto: quando il cliente ha una polizza con una compagnia che
 *    l'agenzia non gestisce, l'operatore scrive il *nome della compagnia* nel
 *    campo del numero. `Prima.it` risulta su 73 clienti diversi. Senza questa
 *    regola un utente vedrebbe le auto di altre 72 famiglie.
 * 3. **Solo persone fisiche con codice fiscale valido** (decisione del cliente
 *    del 2026-11-11). Le partite IVA e gli identificativi di altra forma
 *    restano fuori finche' non si capisce come sono compilati.
 * 4. Il codice fiscale si prende da `clienti.csv`; se li' non c'e', si legge
 *    **dall'ID anagrafica**, che nel 76% dei casi *e'* il codice fiscale.
 *
 * Nomi dei file: in v7 sono minuscoli (`polizze.csv`), in v10 capitalizzati
 * (`Polizze.csv`). Si cercano senza distinguere maiuscole e minuscole, o
 * l'importer funzionerebbe con una sola delle due versioni sui filesystem che
 * il caso lo distinguono — cioe' sul server.
 */
final class SsfImportService
{
    /** Stato che identifica una polizza in corso. */
    private const STATO_ATTIVA = 'AT';

    /**
     * Codici di frazionamento: sono il numero di rate all'anno. Il `9` e' il
     * premio unico, confermato da SSF via mail il 2026-11-11.
     */
    private const FRAZIONAMENTO = [
        '1' => 'Annuale',
        '2' => 'Semestrale',
        '3' => 'Quadrimestrale',
        '4' => 'Trimestrale',
        '6' => 'Bimestrale',
        '12' => 'Mensile',
        '9' => 'Premio unico',
    ];

    /** Lunghezze della tabella `polizze`: si tronca qui, non si lascia troncare al DB. */
    private const LIMITI = [
        'contraente' => 255, 'n_polizza' => 100, 'compagnia' => 255, 'ramo' => 255,
        'prodotto' => 255, 'targa' => 30, 'frazionamento' => 50, 'stato_polizza' => 100,
    ];

    private const LOTTO = 500;

    /**
     * @return array<string, mixed> resoconto: quante importate e, soprattutto,
     *                              quante scartate e per quale motivo
     */
    public function importa(string $zipPath, int $agenziaId, bool $sostituisci = true): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Archivio non apribile: {$zipPath}");
        }

        try {
            $clienti = $this->leggiClienti($zip);
            $targhe = $this->leggiTarghe($zip);
            $proprietari = $this->contaProprietari($zip);

            $resoconto = $this->scrivi($zip, $agenziaId, $sostituisci, $clienti, $targhe, $proprietari);
        } finally {
            $zip->close();
        }

        return $resoconto;
    }

    // ─── lettura dell'archivio ───────────────────────────────────────────────

    /**
     * Apre un file dentro lo ZIP cercandolo senza distinguere maiuscole e
     * minuscole.
     *
     * @return resource
     */
    private function apri(ZipArchive $zip, string $nome)
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $interno = (string) $zip->getNameIndex($i);
            if (strcasecmp(basename($interno), $nome) !== 0) {
                continue;
            }
            $stream = $zip->getStream($interno);
            if ($stream === false) {
                throw new RuntimeException("File non leggibile nell'archivio: {$interno}");
            }

            return $stream;
        }

        throw new RuntimeException("File mancante nell'archivio: {$nome}");
    }

    /**
     * Scorre un CSV dello ZIP restituendo ogni riga come mappa colonna => valore.
     *
     * Si legge **per nome di colonna, mai per posizione**: fra v7 e v10 le
     * colonne sono le stesse ma in ordine diverso (`RAMO_CMP` e `RAMO_SHARE` si
     * scambiano), quindi un parser posizionale funzionerebbe con una versione e
     * mescolerebbe i campi con l'altra.
     *
     * @return \Generator<int, array<string, string>>
     */
    private function righe(ZipArchive $zip, string $nome): \Generator
    {
        $stream = $this->apri($zip, $nome);
        try {
            $testata = fgetcsv($stream, 0, ';');
            if ($testata === false) {
                return;
            }
            $testata = array_map(static fn ($c) => trim((string) $c), $testata);
            // I CSV di SSF sono UTF-8 **con BOM**: senza toglierlo il primo nome
            // di colonna diventa "\u{FEFF}TP_RECORD_SHARE" e tutte le ricerche
            // per nome su quella colonna falliscono. Si toglie qui e non dallo
            // stream perche' quello di ZipArchive non si puo' riavvolgere.
            if ($testata !== []) {
                $testata[0] = preg_replace('/^\xEF\xBB\xBF/', '', $testata[0]) ?? $testata[0];
            }

            while (($riga = fgetcsv($stream, 0, ';')) !== false) {
                if ($riga === [null]) {
                    continue;   // riga vuota
                }
                $mappa = [];
                foreach ($testata as $i => $colonna) {
                    $mappa[$colonna] = isset($riga[$i]) ? trim((string) $riga[$i]) : '';
                }
                yield $mappa;
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return array<string, array{cf: string, nome: string}> */
    private function leggiClienti(ZipArchive $zip): array
    {
        $out = [];
        foreach ($this->righe($zip, 'clienti.csv') as $r) {
            $id = $r['ID_ANAGRAFICA_EXP'] ?? '';
            if ($id !== '') {
                $out[$id] = [
                    'cf' => strtoupper($r['CODICE_FISCALE'] ?? ''),
                    'nome' => $r['RAGIONE_SOCIALE'] ?? '',
                ];
            }
        }

        return $out;
    }

    /** @return array<string, list<string>> numero polizza => targhe */
    private function leggiTarghe(ZipArchive $zip): array
    {
        $out = [];
        foreach ($this->righe($zip, 'polizzealtro.csv') as $r) {
            $numero = $r['NUMERO_POLIZZA_CMP'] ?? '';
            $targa = strtoupper($r['TARGA'] ?? '');
            if ($numero !== '' && $targa !== '' && ! in_array($targa, $out[$numero] ?? [], true)) {
                $out[$numero][] = $targa;
            }
        }

        return $out;
    }

    /**
     * Quanti clienti distinti usano ciascun numero di polizza.
     *
     * Serve per la regola dei segnaposto, e richiede una passata in piu' sul
     * file: non si puo' sapere se un numero e' sospetto finche' non si e' visto
     * tutto l'export.
     *
     * @return array<string, int>
     */
    private function contaProprietari(ZipArchive $zip): array
    {
        $visti = [];
        foreach ($this->righe($zip, 'polizze.csv') as $r) {
            $numero = $r['NUMERO_POLIZZA_CMP'] ?? '';
            $anagrafica = $r['ID_ANAGRAFICA_EXP'] ?? '';
            if ($numero !== '') {
                $visti[$numero][$anagrafica] = true;
            }
        }

        return array_map('count', $visti);
    }

    // ─── scrittura ───────────────────────────────────────────────────────────

    /**
     * @param  array<string, array{cf: string, nome: string}>  $clienti
     * @param  array<string, list<string>>  $targhe
     * @param  array<string, int>  $proprietari
     * @return array<string, mixed>
     */
    private function scrivi(
        ZipArchive $zip,
        int $agenziaId,
        bool $sostituisci,
        array $clienti,
        array $targhe,
        array $proprietari,
    ): array {
        if ($sostituisci) {
            Polizza::where('id_agenzia', $agenziaId)->delete();
        }

        $conteggi = [
            'lette' => 0, 'importate' => 0,
            'scartate_non_attive' => 0, 'scartate_segnaposto' => 0,
            'scartate_senza_cf' => 0, 'scartate_senza_numero' => 0,
            'senza_scadenza_titolo' => 0, 'targhe_troncate' => 0,
        ];
        $lotto = [];

        foreach ($this->righe($zip, 'polizze.csv') as $r) {
            $conteggi['lette']++;

            if (($r['COD_STATO_SHARE'] ?? '') !== self::STATO_ATTIVA) {
                $conteggi['scartate_non_attive']++;

                continue;
            }

            $numero = $r['NUMERO_POLIZZA_CMP'] ?? '';
            if ($numero === '') {
                $conteggi['scartate_senza_numero']++;

                continue;
            }
            if (($proprietari[$numero] ?? 1) > 1) {
                $conteggi['scartate_segnaposto']++;

                continue;
            }

            $anagrafica = $r['ID_ANAGRAFICA_EXP'] ?? '';
            $cf = $this->codiceFiscale($anagrafica, $clienti);
            if ($cf === null) {
                $conteggi['scartate_senza_cf']++;

                continue;
            }

            $scadenzaTitolo = $this->data($r['SCADENZA_INCASSATO'] ?? '');
            if ($scadenzaTitolo === '') {
                // Si importa lo stesso: manca solo il conto alla rovescia, e la
                // schermata dell'app regge il campo vuoto senza problemi.
                $conteggi['senza_scadenza_titolo']++;
            }

            $elencoTarghe = $targhe[$numero] ?? [];
            $targa = implode(', ', $elencoTarghe);
            if (mb_strlen($targa) > self::LIMITI['targa']) {
                $conteggi['targhe_troncate']++;
            }

            $lotto[] = [
                'id_agenzia' => $agenziaId,
                'cf' => $cf,
                'contraente' => $this->tronca($clienti[$anagrafica]['nome'] ?? '', 'contraente'),
                'n_polizza' => $this->tronca($numero, 'n_polizza'),
                'compagnia' => $this->tronca($r['COMPAGNIA_ANIA'] ?? '', 'compagnia'),
                'ramo' => $this->tronca($r['RAMO_CMP'] ?? '', 'ramo'),
                'prodotto' => $this->tronca($r['PRODOTTO_CMP'] ?? '', 'prodotto'),
                'targa' => $this->tronca($targa, 'targa'),
                'frazionamento' => $this->frazionamento($r['FRAZIONAMENTO_SHARE'] ?? ''),
                'data_decorrenza' => $this->data($r['EFFETTO'] ?? ''),
                'data_scadenza_titolo' => $scadenzaTitolo,
                'data_scadenza_contratto' => $this->data($r['SCADENZA_EFFETTIVA'] ?? ''),
                'stato_polizza' => 'Attiva',
            ];
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
        Polizza::upsert(
            $lotto,
            ['n_polizza', 'id_agenzia'],
            ['cf', 'contraente', 'compagnia', 'ramo', 'prodotto', 'targa', 'frazionamento',
                'data_decorrenza', 'data_scadenza_titolo', 'data_scadenza_contratto', 'stato_polizza'],
        );
    }

    // ─── conversioni ─────────────────────────────────────────────────────────

    /**
     * Codice fiscale del contraente, o null se non e' una persona fisica.
     *
     * Si prova prima `clienti.csv`; se li' l'anagrafica non c'e' o non ha il
     * codice fiscale, si guarda l'ID stesso, che nella gran parte dei casi *e'*
     * il codice fiscale (a volte col prefisso `PF_`). Questo recupera piu' della
     * meta' delle polizze attive che altrimenti resterebbero orfane.
     *
     * @param  array<string, array{cf: string, nome: string}>  $clienti
     */
    private function codiceFiscale(string $anagrafica, array $clienti): ?string
    {
        foreach ([$clienti[$anagrafica]['cf'] ?? '', $anagrafica] as $candidato) {
            $c = strtoupper(trim($candidato));
            if (str_starts_with($c, 'PF_')) {
                $c = substr($c, 3);
            }
            if (preg_match('/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/', $c) === 1) {
                return $c;
            }
        }

        return null;
    }

    /** Da `DD/MM/YYYY` a `YYYY-MM-DD`, che e' il formato che l'app sa leggere. */
    private function data(string $valore): string
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', trim($valore), $m) === 1) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return '';
    }

    private function frazionamento(string $codice): string
    {
        return self::FRAZIONAMENTO[trim($codice)] ?? '';
    }

    private function tronca(string $valore, string $campo): string
    {
        return mb_substr($valore, 0, self::LIMITI[$campo]);
    }
}
