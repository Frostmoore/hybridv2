<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Backup giornaliero del DB in JSON con retention 7 file.
 * Porting della prima parte di cron_scadenze.php legacy: stesso formato
 * { tabella: [righe…] } (leggibile da hybrid:import-backup).
 */
class BackupDatabase extends Command
{
    protected $signature = 'hybrid:backup-db';

    protected $description = 'Dump giornaliero del database in storage/app/backups (retention 7)';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/backup_'.date('Y-m-d').'.json';

        // Cross-driver (MySQL in prod, SQLite nei test): solo le tabelle
        // del database corrente (con certi permessi getTables vede anche
        // gli altri schema del server)
        $currentDb = DB::getDatabaseName();
        $tables = collect(\Illuminate\Support\Facades\Schema::getTables())
            ->filter(fn (array $t) => ($t['schema'] ?? null) === null
                || in_array($t['schema'], [$currentDb, 'main'], true))
            ->pluck('name')
            ->all();

        $dump = [];
        foreach ($tables as $table) {
            $dump[$table] = array_map(fn ($r) => (array) $r, DB::table($table)->get()->all());
        }

        File::put($file, (string) json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Retention: tieni i 7 più recenti
        $files = collect(File::glob($dir.'/backup_*.json'))
            ->sortByDesc(fn ($f) => File::lastModified($f))
            ->values();
        foreach ($files->slice(7) as $old) {
            File::delete($old);
        }

        Log::channel('cron')->info("Backup DB completato → $file (".count($tables).' tabelle)');
        $this->info("Backup OK → $file");

        return self::SUCCESS;
    }
}
