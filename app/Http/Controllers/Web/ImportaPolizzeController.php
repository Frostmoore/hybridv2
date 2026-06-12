<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\PolizzaImportata;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Sistema A: /importa_polizze.php (wizard 2 step, sessione admin)
 * + POST res/import_process.php (AJAX JSON) → tabella `polizze_importate`.
 * Porting di legacy importa_polizze.php + res/import_process.php.
 */
class ImportaPolizzeController extends Controller
{
    public const STANDARD_FIELDS = [
        'cf' => 'Codice Fiscale',
        'numero_polizza' => 'Numero Polizza',
        'desc_ramo' => 'Ramo / Tipo',
        'desc_prodotto' => 'Prodotto',
        'compagnia' => 'Compagnia',
        'targa' => 'Targa',
        'data_effetto' => 'Data Decorrenza',
        'data_scadenza' => 'Data Scadenza',
        'data_effetto_titolo' => 'Data Effetto Titolo (cron)',
        'stato' => 'Stato',
        'premio' => 'Premio (€)',
    ];

    private const DATE_FIELDS = ['data_effetto', 'data_scadenza', 'data_effetto_titolo'];

    // ─── GET|POST importa_polizze.php ────────────────────────────────────

    public function page(Request $request)
    {
        $step = 1;
        $error = '';
        $columns = [];
        $preview = [];
        $autoGuess = [];

        if ($request->isMethod('POST') && $request->input('step') === '1') {
            $agenziaId = (int) $request->input('id_agenzia', 0);
            $rawDelim = (string) $request->input('delimiter', ';');
            $delimiter = strlen($rawDelim) === 1 ? $rawDelim : ';';
            $encoding = (string) $request->input('encoding', 'UTF-8');
            $onDup = in_array($request->input('on_duplicate'), ['skip', 'update', 'always'], true)
                ? (string) $request->input('on_duplicate') : 'skip';

            $file = $request->file('polizze_file');

            if ($agenziaId <= 0) {
                $error = 'Seleziona un\'agenzia.';
            } elseif ($file === null || ! $file->isValid()) {
                $error = 'Errore upload: nessun file selezionato o caricamento fallito.';
            } else {
                $ext = strtolower($file->getClientOriginalExtension());
                if (! in_array($ext, ['csv', 'xml'], true)) {
                    $error = 'Formato non supportato. Carica un file CSV o XML.';
                } else {
                    $tmpDir = storage_path('app/import_tmp');
                    File::ensureDirectoryExists($tmpDir);
                    $tmpPath = $tmpDir.DIRECTORY_SEPARATOR.uniqid('imp_', true).'.'.$ext;
                    $file->move($tmpDir, basename($tmpPath));

                    if (strtoupper($encoding) !== 'UTF-8') {
                        $raw = (string) file_get_contents($tmpPath);
                        $conv = @mb_convert_encoding($raw, 'UTF-8', $encoding);
                        if ($conv !== false) {
                            file_put_contents($tmpPath, $conv);
                        }
                    }

                    $request->session()->put([
                        'imp_file' => $tmpPath,
                        'imp_type' => $ext,
                        'imp_agenzia' => $agenziaId,
                        'imp_delimiter' => $delimiter,
                        'imp_origname' => $file->getClientOriginalName(),
                        'imp_ondup' => $onDup,
                    ]);

                    if ($ext === 'csv') {
                        $rows = SpreadsheetReader::csv($tmpPath, $delimiter);
                        $columns = $rows[0] ?? [];
                        $preview = array_slice($rows, 1, 5);
                    } else {
                        [$columns, $xmlRows] = SpreadsheetReader::xml($tmpPath);
                        $preview = array_map(array_values(...), array_slice($xmlRows, 0, 5));
                    }

                    if ($columns === []) {
                        $error = 'Impossibile rilevare le colonne. Verifica il file e il delimitatore scelto.';
                        @unlink($tmpPath);
                        $request->session()->forget('imp_file');
                    } else {
                        $step = 2;
                        $autoGuess = $this->autoGuessMapping($columns);
                    }
                }
            }
        }

        return view('admin.importa_polizze', [
            'step' => $step,
            'error' => $error,
            'columns' => $columns,
            'preview' => $preview,
            'autoGuess' => $autoGuess,
            'agenzie' => \App\Models\AgenziaNew::orderBy('nome_agenzia')->get(['id', 'nome_agenzia']),
            'targetFields' => self::STANDARD_FIELDS,
        ]);
    }

    // ─── POST res/import_process.php (AJAX JSON) ─────────────────────────

    public function process(Request $request)
    {
        set_time_limit(0);

        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'Payload JSON non valido.'], 400);
        }

        $mapping = (array) ($payload['mapping'] ?? []);
        $datefmt = (array) ($payload['datefmt'] ?? []);

        $tmpPath = (string) $request->session()->get('imp_file', '');
        $fileType = (string) $request->session()->get('imp_type', '');
        $agenziaId = (int) $request->session()->get('imp_agenzia', 0);
        $delimiter = (string) $request->session()->get('imp_delimiter', ';');
        $origName = (string) $request->session()->get('imp_origname', '');
        $onDup = (string) $request->session()->get('imp_ondup', 'skip');

        if ($tmpPath === '' || ! file_exists($tmpPath) || $agenziaId <= 0) {
            return response()->json(['error' => 'Sessione scaduta o file non trovato. Ricomincia l\'importazione.']);
        }

        // Righe come mappe colonna => valore
        if ($fileType === 'csv') {
            $all = SpreadsheetReader::csv($tmpPath, $delimiter);
            $headers = array_shift($all) ?? [];
            $rows = array_map(
                fn (array $r) => array_combine($headers, array_pad(array_slice($r, 0, count($headers)), count($headers), '')),
                $all,
            );
        } else {
            [, $rows] = SpreadsheetReader::xml($tmpPath);
        }

        $inseriti = 0;
        $aggiornati = 0;
        $saltati = 0;
        $errori = 0;
        $log = [];

        foreach ($rows as $i => $rowData) {
            try {
                $esito = $this->processRow($rowData, $mapping, $datefmt, $agenziaId, $origName, $onDup);
            } catch (\Throwable $e) {
                $esito = 'errore';
                $log[] = '['.date('H:i:s').'] Riga '.($i + 2).': '.$e->getMessage();
            }
            match ($esito) {
                'inserito' => $inseriti++,
                'aggiornato' => $aggiornati++,
                'saltato' => $saltati++,
                default => $errori++,
            };
        }

        @unlink($tmpPath);
        $request->session()->forget(['imp_file', 'imp_type', 'imp_agenzia', 'imp_delimiter', 'imp_origname', 'imp_ondup']);

        $log[] = '['.date('H:i:s')."] Import completato: $inseriti inseriti, $aggiornati aggiornati, $saltati saltati, $errori errori.";

        return response()->json([
            'inseriti' => $inseriti,
            'aggiornati' => $aggiornati,
            'saltati' => $saltati,
            'errori' => $errori,
            'log' => $log,
        ]);
    }

    // ─── Helpers (porting da import_process.php) ─────────────────────────

    /** @param  array<string, string>  $rowData */
    private function processRow(array $rowData, array $mapping, array $datefmt, int $agenziaId, string $origName, string $onDup): string
    {
        $record = [];
        foreach (array_keys(self::STANDARD_FIELDS) as $field) {
            $srcCol = (string) ($mapping[$field] ?? '');
            $val = $srcCol !== '' && array_key_exists($srcCol, $rowData) ? trim((string) $rowData[$srcCol]) : '';

            if (in_array($field, self::DATE_FIELDS, true)) {
                $record[$field] = $this->parseDate($val !== '' ? $val : null, (string) ($datefmt[$field] ?? 'auto'));
            } elseif ($field === 'premio') {
                // Fix vs legacy: "1.234,56" perdeva le migliaia (diventava 1.23).
                // Formato italiano: '.' migliaia, ',' decimali.
                $val = (string) preg_replace('/[^\d,.\-]/', '', $val);
                if (str_contains($val, ',')) {
                    $val = str_replace('.', '', $val);      // rimuovi separatori migliaia
                    $val = str_replace(',', '.', $val);     // virgola → punto decimale
                }
                $record[$field] = $val !== '' ? (float) $val : null;
            } else {
                $record[$field] = $val;
            }
        }

        // Fallback legacy: data_effetto_titolo ← data_scadenza
        if ($record['data_effetto_titolo'] === null && $record['data_scadenza'] !== null) {
            $record['data_effetto_titolo'] = $record['data_scadenza'];
        }

        $cfNorm = strtoupper(trim((string) $record['cf']));
        $clienteId = $cfNorm !== ''
            ? Cliente::where('cf', $cfNorm)->where('agenziaid', $agenziaId)->value('id')
            : null;

        $numPol = (string) $record['numero_polizza'];
        $attributes = [
            'cliente_id' => $clienteId,
            'cf' => $cfNorm,
            'desc_ramo' => $record['desc_ramo'],
            'desc_prodotto' => $record['desc_prodotto'],
            'compagnia' => $record['compagnia'],
            'targa' => $record['targa'],
            'data_effetto' => $record['data_effetto'],
            'data_scadenza' => $record['data_scadenza'],
            'data_effetto_titolo' => $record['data_effetto_titolo'],
            'stato' => $record['stato'],
            'premio' => $record['premio'],
            'raw_data' => json_encode($rowData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'nome_file' => $origName,
        ];

        if ($onDup !== 'always' && $numPol !== '') {
            $existing = PolizzaImportata::where('id_agenzia', $agenziaId)
                ->where('numero_polizza', $numPol)
                ->first();
            if ($existing !== null) {
                if ($onDup === 'skip') {
                    return 'saltato';
                }
                $existing->fill($attributes)->save();

                return 'aggiornato';
            }
        }

        PolizzaImportata::create($attributes + [
            'id_agenzia' => $agenziaId,
            'numero_polizza' => $numPol,
            'fonte' => 'import',
        ]);

        return 'inserito';
    }

    /** parseDate legacy: 'auto' prova i formati comuni, poi strtotime. */
    private function parseDate(?string $value, string $fmt = 'auto'): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);

        if ($fmt === 'auto') {
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Ymd', 'd/m/y', 'Y/m/d', 'm/d/Y', 'd.m.Y'] as $f) {
                $dt = \DateTime::createFromFormat($f, $value);
                if ($dt && $dt->format($f) === $value) {
                    return $dt->format('Y-m-d');
                }
            }
            $ts = @strtotime($value);

            return $ts ? date('Y-m-d', $ts) : null;
        }

        $dt = \DateTime::createFromFormat($fmt, $value);

        return $dt ? $dt->format('Y-m-d') : null;
    }

    /** @return array<string, string> campo → colonna sorgente */
    private function autoGuessMapping(array $columns): array
    {
        $rules = [
            'cf' => ['cf', 'codicefiscale', 'codice_fiscale', 'fiscal', 'fiscalcode', 'codfisc'],
            'numero_polizza' => ['numero_polizza', 'numpolizza', 'num_polizza', 'numeropolizza', 'polizza', 'num', 'policy', 'policy_number', 'nrpolizza'],
            'desc_ramo' => ['ramo', 'desc_ramo', 'tipo', 'branch', 'type', 'categoria', 'tipo_polizza'],
            'desc_prodotto' => ['prodotto', 'desc_prodotto', 'product', 'descrizione_prodotto', 'prodotto_assicurativo'],
            'compagnia' => ['compagnia', 'company', 'assicurazione', 'compagnia_assicurativa', 'assicuratrice'],
            'targa' => ['targa', 'plate', 'veicolo_targa'],
            'data_effetto' => ['decorrenza', 'data_decorrenza', 'data_effetto', 'datadecorrenza', 'effetto', 'inizio', 'data_inizio', 'datainizio'],
            'data_scadenza' => ['scadenza', 'data_scadenza', 'datascadenza', 'expiry', 'fine', 'data_fine', 'datafine', 'scad'],
            'data_effetto_titolo' => ['effettotitolo', 'data_effetto_titolo', 'titolo', 'rinnovo', 'data_rinnovo', 'datarinnovo'],
            'stato' => ['stato', 'status', 'state', 'attiva', 'vigente'],
            'premio' => ['premio', 'importo', 'prezzo', 'price', 'amount', 'rata', 'premio_annuo'],
        ];

        $guesses = [];
        $usedCols = [];
        foreach ($rules as $target => $keywords) {
            foreach ($columns as $col) {
                if (in_array($col, $usedCols, true)) {
                    continue;
                }
                $colNorm = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $col));
                foreach ($keywords as $kw) {
                    if ($colNorm === $kw || str_contains($colNorm, $kw)) {
                        $guesses[$target] = $col;
                        $usedCols[] = $col;

                        continue 3;
                    }
                }
            }
        }

        return $guesses;
    }
}
