<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importa nel DB locale un backup JSON giornaliero del server legacy
 * (il dump prodotto da cron_scadenze.php: { tabella: [righe…], … }).
 *
 * Pensato per lo sviluppo; l'importer definitivo della fase 9 leggerà
 * l'export NDJSON dell'exporter dedicato.
 *
 *   php artisan hybrid:import-backup ../legacy/public_html/backup/backup_2026-06-11.json
 */
class ImportLegacyBackup extends Command
{
    protected $signature = 'hybrid:import-backup {file : Path del backup JSON legacy} {--no-truncate : Non svuotare le tabelle prima}';

    protected $description = 'Importa un backup JSON del server legacy nel database locale (dev)';

    /** Colonne DATETIME: '' → NULL, NULL preservato. */
    private const DATETIME_COLUMNS = [
        'clienti' => ['firstlogin', 'lastlogin'],
        'operatori' => ['first_login', 'last_login'],
        'notifiche_generali' => ['notifica_scadenza'],
        'delete_requests' => ['expiration'],
    ];

    /** Colonne INTERE (nullable): '' → NULL, NULL preservato. */
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

    /** Tabelle legacy deliberatamente NON importate. */
    private const SKIP = ['agenzie']; // morta (decisione §7.3)

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File non trovato: $file");

            return self::FAILURE;
        }

        $dump = json_decode((string) file_get_contents($file), true);
        if (! is_array($dump)) {
            $this->error('JSON non valido.');

            return self::FAILURE;
        }

        foreach ($dump as $table => $rows) {
            if (in_array($table, self::SKIP, true)) {
                $this->warn("− $table: saltata (tabella morta)");

                continue;
            }
            if (! Schema::hasTable($table)) {
                $this->warn("− $table: non esiste nello schema nuovo, saltata");

                continue;
            }
            if (! is_array($rows)) {
                continue;
            }

            if (! $this->option('no-truncate')) {
                DB::table($table)->truncate();
            }

            $datetimeCols = self::DATETIME_COLUMNS[$table] ?? [];
            $intCols = self::INT_COLUMNS[$table] ?? [];
            $clean = array_map(function (array $row) use ($datetimeCols, $intCols) {
                foreach ($row as $col => $value) {
                    if (in_array($col, $datetimeCols, true) || in_array($col, $intCols, true)) {
                        // datetime/interi: '' non è valido → NULL
                        if ($value === '') {
                            $row[$col] = null;
                        }
                    } elseif ($value === null) {
                        // stringhe: il legacy permetteva NULL, lo schema nuovo
                        // usa NOT NULL DEFAULT '' (s() li rende comunque '')
                        $row[$col] = '';
                    }
                }

                return $row;
            }, $rows);

            foreach (array_chunk($clean, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }

            $imported = DB::table($table)->count();
            $expected = count($rows);
            $flag = $imported === $expected ? '✓' : '⚠';
            $this->info("$flag $table: $imported/$expected righe");
        }

        return self::SUCCESS;
    }
}
