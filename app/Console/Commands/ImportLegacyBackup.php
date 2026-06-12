<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LegacyRowSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importa nel DB locale un backup JSON giornaliero (il dump prodotto da
 * cron_scadenze.php legacy o da hybrid:backup-db: { tabella: [righe…], … }).
 *
 * Pensato per lo sviluppo; la migrazione definitiva usa hybrid:import-legacy
 * con l'export NDJSON di export_db.php.
 *
 *   php artisan hybrid:import-backup ../legacy/public_html/backup/backup_2026-06-11.json
 */
class ImportLegacyBackup extends Command
{
    protected $signature = 'hybrid:import-backup {file : Path del backup JSON legacy} {--no-truncate : Non svuotare le tabelle prima}';

    protected $description = 'Importa un backup JSON del server legacy nel database locale (dev)';

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
            if (in_array($table, LegacyRowSanitizer::SKIP_TABLES, true)) {
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

            $clean = array_map(
                fn (array $row) => LegacyRowSanitizer::sanitize($table, $row),
                $rows,
            );

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
