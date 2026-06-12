<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LegacyRowSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importer DEFINITIVO della migrazione dati: legge l'export NDJSON prodotto
 * da export_db.php (deployato sul server legacy) e popola il nuovo DB.
 *
 * Formato NDJSON (una riga JSON per record):
 *   {"_meta":{...}}                        — intestazione export
 *   {"_table":{"name":"clienti","count":N,"create":"..."}}
 *   {"t":"clienti","r":{...riga...}}       — N righe
 *
 * Idempotente: ogni tabella viene svuotata prima dell'insert (salvo
 * --no-truncate). Gli id originali vengono preservati.
 *
 *   php artisan hybrid:import-legacy storage/app/export_hybrid.ndjson
 */
class ImportLegacyExport extends Command
{
    protected $signature = 'hybrid:import-legacy
        {file : Path del file NDJSON prodotto da export_db.php}
        {--no-truncate : Non svuotare le tabelle prima}
        {--no-rewrite : Non riscrivere i domini hybridandgogsv.it → v2}';

    protected $description = 'Importa nel nuovo DB l\'export NDJSON del server legacy';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File non trovato: $file");

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            $this->error('Impossibile aprire il file.');

            return self::FAILURE;
        }

        $rewrite = ! $this->option('no-rewrite');
        $truncate = ! $this->option('no-truncate');

        $currentTable = null;
        $skipTable = false;
        $declared = [];        // tabella → count dichiarato
        $imported = [];        // tabella → righe inserite
        $unknownTables = [];   // tabelle non presenti nello schema nuovo
        $batch = [];
        $line = 0;

        $flush = function () use (&$batch, &$currentTable, &$imported): void {
            if ($batch !== [] && $currentTable !== null) {
                DB::table($currentTable)->insert($batch);
                $imported[$currentTable] = ($imported[$currentTable] ?? 0) + count($batch);
                $batch = [];
            }
        };

        while (($raw = fgets($handle)) !== false) {
            $line++;
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                $this->warn("Riga $line: JSON non valido, saltata.");

                continue;
            }

            if (isset($decoded['_meta'])) {
                $this->info('Export del '.($decoded['_meta']['exported_at'] ?? '?').' — DB '.($decoded['_meta']['db'] ?? '?'));

                continue;
            }

            if (isset($decoded['_table'])) {
                $flush();
                $name = (string) ($decoded['_table']['name'] ?? '');
                $declared[$name] = (int) ($decoded['_table']['count'] ?? 0);

                if (in_array($name, LegacyRowSanitizer::SKIP_TABLES, true)) {
                    $this->warn("− $name: saltata (tabella morta)");
                    $skipTable = true;
                    $currentTable = null;

                    continue;
                }
                if (in_array($name, LegacyRowSanitizer::PROTECTED_TABLES, true)) {
                    $this->warn("− $name: saltata (tabella interna Laravel)");
                    $unknownTables[] = $name;
                    $skipTable = true;
                    $currentTable = null;

                    continue;
                }
                if (! Schema::hasTable($name)) {
                    $this->warn("− $name: non esiste nello schema nuovo, saltata");
                    $unknownTables[] = $name;
                    $skipTable = true;
                    $currentTable = null;

                    continue;
                }

                $currentTable = $name;
                $skipTable = false;
                if ($truncate) {
                    DB::table($name)->truncate();
                }
                $imported[$name] = 0;

                continue;
            }

            if (isset($decoded['t'], $decoded['r']) && is_array($decoded['r'])) {
                if ($skipTable || $decoded['t'] !== $currentTable) {
                    continue;
                }

                $row = LegacyRowSanitizer::sanitize($currentTable, $decoded['r']);
                if ($rewrite) {
                    $row = LegacyRowSanitizer::rewriteDomains($row);
                }
                $batch[] = $row;

                if (count($batch) >= 500) {
                    $flush();
                }
            }
        }
        $flush();
        fclose($handle);

        // ── Report finale ────────────────────────────────────────────────
        $this->newLine();
        $this->info('═══ Report import ═══');
        $ok = true;
        foreach ($declared as $table => $count) {
            if (in_array($table, LegacyRowSanitizer::SKIP_TABLES, true)) {
                $this->line("  − $table: $count righe NON importate (tabella morta)");

                continue;
            }
            if (in_array($table, $unknownTables, true)) {
                $this->line("  − $table: $count righe NON importate (assente nello schema nuovo)");

                continue;
            }
            $got = $imported[$table] ?? 0;
            $inDb = Schema::hasTable($table) ? DB::table($table)->count() : 0;
            $flag = $got === $count ? '✓' : '⚠';
            if ($got !== $count) {
                $ok = false;
            }
            $this->line("  $flag $table: $got/$count importate (in DB: $inDb)");
        }

        if (! $ok) {
            $this->warn('Alcune tabelle non combaciano col conteggio dichiarato.');

            return self::FAILURE;
        }
        $this->info('Import completato: tutti i conteggi combaciano.');

        return self::SUCCESS;
    }
}
