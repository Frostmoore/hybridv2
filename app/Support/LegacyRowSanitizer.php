<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sanificazione delle righe provenienti dal DB legacy verso lo schema nuovo:
 * - colonne DATETIME/inte: '' → NULL (il legacy le permetteva vuote)
 * - colonne stringa: NULL → '' (lo schema nuovo usa NOT NULL DEFAULT '')
 * - tabelle morte da saltare
 * Usata da hybrid:import-backup e hybrid:import-legacy.
 */
final class LegacyRowSanitizer
{
    /** Tabelle legacy deliberatamente NON importate (decisione §7.3). */
    public const SKIP_TABLES = ['agenzie', 'accounts'];

    /** Tabelle interne Laravel: mai sovrascritte da un import. */
    public const PROTECTED_TABLES = [
        'migrations', 'users', 'password_reset_tokens', 'sessions',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'scadenze_notificate',
    ];

    /** Colonne DATETIME/DATE per tabella: '' → NULL. */
    private const DATETIME_COLUMNS = [
        'clienti' => ['firstlogin', 'lastlogin'],
        'operatori' => ['first_login', 'last_login'],
        'notifiche_generali' => ['notifica_scadenza'],
        'delete_requests' => ['expiration'],
        'polizze_importate' => ['data_effetto', 'data_scadenza', 'data_effetto_titolo', 'importato_il'],
    ];

    /**
     * Colonne data a formato misto nel legacy (VARCHAR): normalizzate al
     * canonico `Y-m-d H:i:s`. `dataora` è esposta all'app; le `data_denuncia`
     * sono d'archivio. Valori non riconducibili a una data restano invariati.
     * Vedi critics.md (date in formati misti).
     */
    private const DATE_COLUMNS = [
        'notifiche' => ['dataora'],
        'sinistri' => ['data_denuncia'],
        'preventivi' => ['data_denuncia'],
        'documenti' => ['data_denuncia'],
    ];

    /** Formati legacy noti, provati in ordine (i più specifici prima). */
    private const DATE_INPUT_FORMATS = [
        'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d',
        'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
        'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
    ];

    /**
     * Colonne booleane a formato misto nel legacy (`'on'` dai form web, `'1'`
     * dalla v2): normalizzate al canonico `'1'`/`'0'`. Vedi critics.md.
     */
    private const BOOL_COLUMNS = [
        'sinistri' => ['privacy_denuncia'],
        'preventivi' => ['privacy_denuncia'],
        'documenti' => ['privacy_denuncia'],
    ];

    /** Token riconosciuti come "vero" (consenso dato). */
    private const TRUTHY = ['1', 'on', 'true', 'yes', 'si', 'sì'];

    /**
     * Colonne CSV di username (virgola finale + voci vuote nel legacy):
     * ripulite a CSV canonico senza virgola finale. La lettura resta comunque
     * tollerante (Notifica::csvToList()). Vedi critics.md.
     */
    private const CSV_COLUMNS = [
        'notifiche' => ['destinatari', 'letta_da'],
    ];

    /** Colonne intere (nullable) per tabella: '' → NULL. */
    private const INT_COLUMNS = [
        'clienti' => ['agenziaid'],
        'operatori' => ['agid'],
        'notifiche' => ['agenziaid'],
        'notifiche_generali' => ['notifica_agid'],
        'sinistri' => ['id_agenzia'],
        'preventivi' => ['id_agenzia'],
        'documenti' => ['id_agenzia'],
        'polizze' => ['id_agenzia'],
        'polizze_importate' => ['id_agenzia', 'cliente_id'],
    ];

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function sanitize(string $table, array $row): array
    {
        $datetimeCols = self::DATETIME_COLUMNS[$table] ?? [];
        $intCols = self::INT_COLUMNS[$table] ?? [];

        foreach ($row as $col => $value) {
            if (in_array($col, $datetimeCols, true) || in_array($col, $intCols, true)) {
                if ($value === '') {
                    $row[$col] = null;
                }
            } elseif ($value === null) {
                $row[$col] = '';
            }
        }

        foreach (self::DATE_COLUMNS[$table] ?? [] as $col) {
            if (isset($row[$col]) && is_string($row[$col]) && $row[$col] !== '') {
                $row[$col] = self::normalizeDate($row[$col]);
            }
        }

        foreach (self::BOOL_COLUMNS[$table] ?? [] as $col) {
            if (array_key_exists($col, $row)) {
                $row[$col] = self::normalizeBool($row[$col]);
            }
        }

        foreach (self::CSV_COLUMNS[$table] ?? [] as $col) {
            if (isset($row[$col]) && is_string($row[$col])) {
                $row[$col] = self::normalizeCsv($row[$col]);
            }
        }

        return $row;
    }

    /** CSV di username canonico: trim, niente voci vuote, niente virgola finale. */
    public static function normalizeCsv(string $csv): string
    {
        $parts = array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== '');

        return implode(',', $parts);
    }

    /** Canonicalizza un flag booleano legacy in `'1'` (vero) / `'0'` (falso). */
    public static function normalizeBool(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return in_array(strtolower(trim((string) $value)), self::TRUTHY, true) ? '1' : '0';
    }

    /**
     * Riconduce un valore data legacy al canonico `Y-m-d H:i:s`.
     * Prova i formati noti in ordine; il primo che combacia ESATTAMENTE (nessun
     * warning di rollover) vince. Le date senza ora diventano `… 00:00:00`.
     * Se nessun formato combacia, ritorna il valore invariato (mai distruttivo).
     */
    public static function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        foreach (self::DATE_INPUT_FORMATS as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $value);
            $errors = \DateTime::getLastErrors();
            $clean = $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);

            if ($dt !== false && $clean) {
                if (! str_contains($fmt, 'H')) {
                    $dt->setTime(0, 0, 0);
                }

                return $dt->format('Y-m-d H:i:s');
            }
        }

        return $value;
    }

    /**
     * Riscrive i riferimenti al vecchio dominio nei valori stringa
     * (es. URL immagini notifiche su agencies.hybridandgogsv.it).
     * Nota: 'hybridandgogsv.it' NON è sottostringa di 'hybridandgogsv2.it',
     * quindi la sostituzione è sicura anche se rieseguita.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function rewriteDomains(array $row): array
    {
        foreach ($row as $col => $value) {
            if (is_string($value) && str_contains($value, 'hybridandgogsv.it')) {
                $row[$col] = str_replace('hybridandgogsv.it', 'hybridandgogsv2.it', $value);
            }
        }

        return $row;
    }
}
