<?php

declare(strict_types=1);

namespace App\Support;

use ZipArchive;

/**
 * Parser CSV/XLSX/XML in puro PHP, porting delle funzioni legacy di
 * import_polizze.php (xlsx) e importa_polizze.php (csv/xml preview).
 */
final class SpreadsheetReader
{
    /**
     * Legge un CSV in righe; gestisce il BOM UTF-8.
     *
     * @return list<list<string>>
     */
    public static function csv(string $path, string $delimiter = ';'): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** Auto-rileva il separatore CSV come il legacy (';' vs ','). */
    public static function detectCsvSeparator(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ';';
        }
        $firstLine = (string) fgets($handle);
        fclose($handle);

        return substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * Parser XLSX minimale (sheet1 + sharedStrings), porting 1:1 dal legacy.
     *
     * @return list<list<string>>|string  righe, o messaggio di errore
     */
    public static function xlsx(string $path): array|string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return 'Impossibile aprire il file XLSX.';
        }

        $shared = [];
        $ssRaw = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssRaw) {
            $ss = simplexml_load_string($ssRaw);
            if ($ss !== false) {
                foreach ($ss->si as $si) {
                    if (isset($si->t)) {
                        $shared[] = (string) $si->t;
                    } else {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $shared[] = $text;
                    }
                }
            }
        }

        $wsRaw = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (! $wsRaw) {
            return 'Foglio sheet1.xml non trovato nel file XLSX.';
        }

        $ws = simplexml_load_string($wsRaw);
        if ($ws === false) {
            return 'XML del foglio non valido.';
        }

        $rows = [];
        foreach ($ws->sheetData->row as $xmlRow) {
            $rowIdx = (int) $xmlRow['r'] - 1;
            $rowData = [];
            foreach ($xmlRow->c as $cell) {
                $ref = (string) $cell['r'];
                $colStr = preg_replace('/[0-9]/', '', $ref);
                $colIdx = self::columnIndex($colStr);
                $type = (string) ($cell['t'] ?? '');
                $val = (string) ($cell->v ?? '');
                if ($type === 's') {
                    $val = $shared[(int) $val] ?? '';
                }
                while (count($rowData) <= $colIdx) {
                    $rowData[] = '';
                }
                $rowData[$colIdx] = $val;
            }
            $rows[$rowIdx] = $rowData;
        }
        ksort($rows);

        return array_values($rows);
    }

    /**
     * Parser XML legacy: struttura piatta o a due livelli.
     * Ritorna [columns, rows] dove rows sono mappe colonna => valore.
     *
     * @return array{0: list<string>, 1: list<array<string, string>>, 2: ?string}
     */
    public static function xml(string $path): array
    {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            return [[], [], null];
        }

        $rootChildren = $xml->children();
        $firstChild = null;
        foreach ($rootChildren as $c) {
            $firstChild = $c;
            break;
        }
        if ($firstChild === null) {
            return [[], [], null];
        }

        $grandFirst = null;
        foreach ($firstChild->children() as $gc) {
            $grandFirst = $gc;
            break;
        }

        if ($grandFirst !== null && $grandFirst->children()->count() > 0) {
            $recordNodes = $firstChild->children();
            $recordTag = $grandFirst->getName();
            $firstRec = $grandFirst;
        } else {
            $recordNodes = $rootChildren;
            $recordTag = $firstChild->getName();
            $firstRec = $firstChild;
        }

        $cols = [];
        foreach ($firstRec->children() as $key => $val) {
            $cols[] = (string) $key;
            foreach ($val->children() as $sk => $sv) {
                $cols[] = $key.'.'.$sk;
            }
        }
        foreach ($firstRec->attributes() as $ak => $av) {
            $cols[] = '@'.$ak;
        }
        $cols = array_values(array_unique($cols));

        $rows = [];
        foreach ($recordNodes as $rec) {
            $rowArr = [];
            foreach ($cols as $col) {
                if (str_starts_with($col, '@')) {
                    $rowArr[$col] = (string) ($rec->attributes()[substr($col, 1)] ?? '');
                } elseif (str_contains($col, '.')) {
                    [$p, $c] = explode('.', $col, 2);
                    $rowArr[$col] = (string) ($rec->$p->$c ?? '');
                } else {
                    $rowArr[$col] = (string) ($rec->$col ?? '');
                }
            }
            $rows[] = $rowArr;
        }

        return [$cols, $rows, $recordTag];
    }

    private static function columnIndex(string $col): int
    {
        $col = strtoupper($col);
        $idx = 0;
        for ($i = 0, $len = strlen($col); $i < $len; $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - 64);
        }

        return $idx - 1;
    }
}
