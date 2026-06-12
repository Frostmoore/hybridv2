<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgenziaNew;
use App\Models\Polizza;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;

/**
 * Sistema B: /import_polizze.php → tabella `polizze`.
 * Porting di legacy import_polizze.php: gate a password (IMPORT_PASSWORD,
 * sessione 4 ore), upload CSV/XLSX, mapping automatico degli header,
 * date seriali Excel, modalità upsert/replace, batch.
 */
class ImportPolizzeController extends Controller
{
    private const SESSION_KEY = 'polizze_auth_time';

    private const COL_ALIASES = [
        'cf' => ['cf', 'codicefiscale', 'codfiscale', 'codfisc', 'fiscale'],
        'contraente' => ['contraente', 'nominativo', 'nomecognome', 'intestatario'],
        'n_polizza' => ['npolizza', 'numeropolizza', 'polizza', 'numpolizza', 'nrpolizza'],
        'compagnia' => ['compagnia', 'impresa', 'assicurazione'],
        'ramo' => ['ramo'],
        'prodotto' => ['prodotto', 'tipoprodotto'],
        'targa' => ['targa', 'teo', 'telaio'],
        'frazionamento' => ['frazionamento', 'fraz'],
        'data_decorrenza' => ['datadecorrenza', 'decorrenza', 'dataeffetto', 'dataizio', 'inizio'],
        'data_scadenza_titolo' => ['datascadenzatitolo', 'scadenzatitolo', 'scadtitolo'],
        'data_scadenza_contratto' => ['datascadenzacontratto', 'scadenzacontratto', 'scadcontratto', 'scadenza'],
        'stato_polizza' => ['statopolizza', 'stato'],
    ];

    // ─── GET|POST import_polizze.php ─────────────────────────────────────

    public function page(Request $request)
    {
        if ($request->query->has('logout')) {
            $request->session()->forget([self::SESSION_KEY]);

            return redirect('import_polizze.php');
        }

        // Login a password
        if ($request->isMethod('POST') && $request->has('pw')) {
            $expected = (string) config('hybrid.import_password');
            if ($expected !== '' && hash_equals($expected, (string) $request->input('pw'))) {
                $request->session()->put(self::SESSION_KEY, time());

                return redirect('import_polizze.php');
            }

            return view('admin.import_polizze_login', ['error' => 'Password non corretta.']);
        }

        if (! $this->authenticated($request)) {
            return view('admin.import_polizze_login', ['error' => '']);
        }

        // Import
        $result = null;
        if ($request->isMethod('POST') && $request->hasFile('import_file')) {
            $result = $this->import($request);
        }

        return view('admin.import_polizze', [
            'agencies' => AgenziaNew::orderBy('nome_agenzia')->get(['id', 'nome_agenzia', 'token_interno']),
            'result' => $result,
        ]);
    }

    private function authenticated(Request $request): bool
    {
        $time = $request->session()->get(self::SESSION_KEY);
        if ($time === null) {
            return false;
        }
        if (time() - (int) $time > 14400) {   // 4 ore come il legacy
            $request->session()->forget([self::SESSION_KEY]);

            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function import(Request $request): array
    {
        $agencyId = (int) $request->input('agency_id', 0);
        $mode = $request->input('mode', 'upsert');
        $file = $request->file('import_file');

        if ($agencyId <= 0) {
            return ['error' => 'Seleziona un\'agenzia.'];
        }
        if ($file === null || ! $file->isValid()) {
            return ['error' => 'Errore upload file.'];
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'csv') {
            $sep = SpreadsheetReader::detectCsvSeparator($file->getRealPath());
            $rows = SpreadsheetReader::csv($file->getRealPath(), $sep);
        } elseif ($ext === 'xlsx') {
            $rows = SpreadsheetReader::xlsx($file->getRealPath());
            if (is_string($rows)) {
                return ['error' => $rows];
            }
        } else {
            return ['error' => 'Formato non supportato. Usa CSV o XLSX.'];
        }

        if ($rows === []) {
            return ['error' => 'File vuoto.'];
        }

        $headers = array_shift($rows);
        $map = $this->mapHeaders($headers);

        if (! isset($map['cf'])) {
            return ['error' => 'Colonna CF non trovata. Aggiungi una colonna "CF" al file.'];
        }
        if (! isset($map['n_polizza'])) {
            return ['error' => 'Colonna N.POLIZZA non trovata.'];
        }

        set_time_limit(0);

        if ($mode === 'replace') {
            Polizza::where('id_agenzia', $agencyId)->delete();
        }

        $g = fn (string $f, array $row): string => isset($map[$f], $row[$map[$f]]) ? trim((string) $row[$map[$f]]) : '';

        $inserted = 0;
        $skipped = 0;
        $batch = [];
        foreach ($rows as $row) {
            $cf = strtoupper($g('cf', $row));
            $nPolizza = $g('n_polizza', $row);
            if ($cf === '' || $nPolizza === '') {
                $skipped++;

                continue;
            }

            $batch[] = [
                'id_agenzia' => $agencyId,
                'cf' => $cf,
                'contraente' => $g('contraente', $row),
                'n_polizza' => $nPolizza,
                'compagnia' => $g('compagnia', $row),
                'ramo' => $g('ramo', $row),
                'prodotto' => $g('prodotto', $row),
                'targa' => $g('targa', $row),
                'frazionamento' => $g('frazionamento', $row),
                'data_decorrenza' => $this->maybeDate('data_decorrenza', $g('data_decorrenza', $row)),
                'data_scadenza_titolo' => $this->maybeDate('data_scadenza_titolo', $g('data_scadenza_titolo', $row)),
                'data_scadenza_contratto' => $this->maybeDate('data_scadenza_contratto', $g('data_scadenza_contratto', $row)),
                'stato_polizza' => $g('stato_polizza', $row),
            ];
            $inserted++;

            if (count($batch) >= 500) {
                $this->upsertBatch($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->upsertBatch($batch);
        }

        return [
            'success' => true,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => 0,
            'total_rows' => count($rows),
            'mapped_cols' => array_keys($map),
            'mode' => $mode,
        ];
    }

    /** @param  list<array<string, mixed>>  $batch */
    private function upsertBatch(array $batch): void
    {
        // Stessa semantica dell'ON DUPLICATE KEY UPDATE legacy
        Polizza::upsert(
            $batch,
            ['n_polizza', 'id_agenzia'],
            ['cf', 'contraente', 'compagnia', 'ramo', 'prodotto', 'targa', 'frazionamento',
                'data_decorrenza', 'data_scadenza_titolo', 'data_scadenza_contratto', 'stato_polizza'],
        );
    }

    /** @return array<string, int> campo → indice colonna */
    private function mapHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            $norm = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $h)));
            foreach (self::COL_ALIASES as $field => $alts) {
                if (in_array($norm, $alts, true) && ! isset($map[$field])) {
                    $map[$field] = $idx;
                    break;
                }
            }
        }

        return $map;
    }

    /** Seriale Excel → dd/mm/yyyy (come il legacy). */
    private function maybeDate(string $field, string $val): string
    {
        if (str_contains($field, 'data') && is_numeric($val)) {
            $n = (float) $val;
            if ($n > 40000 && $n < 80000) {
                return date('d/m/Y', (int) (($n - 25569) * 86400));
            }
        }

        return $val;
    }
}
