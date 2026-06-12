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
    ];

    /** Colonne DATETIME/DATE per tabella: '' → NULL. */
    private const DATETIME_COLUMNS = [
        'clienti' => ['firstlogin', 'lastlogin'],
        'operatori' => ['first_login', 'last_login'],
        'notifiche_generali' => ['notifica_scadenza'],
        'delete_requests' => ['expiration'],
        'polizze_importate' => ['data_effetto', 'data_scadenza', 'data_effetto_titolo', 'importato_il'],
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

        return $row;
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
