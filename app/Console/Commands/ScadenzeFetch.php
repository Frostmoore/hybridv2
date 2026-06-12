<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Services\AssiEasyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Estrae da AssiEasy le DATA_EFFETTO_TITOLO delle polizze vive di ogni
 * cliente e scrive storage/app/scadenze/scadenze_Y-m-d.json.
 * Porting di cron_scadenze.php legacy (parte fetch; il backup è in
 * hybrid:backup-db). Formato record identico:
 * {cliente_id, id_polizza, data_effetto_titolo, os_app_id, os_api_key, playerid}
 */
class ScadenzeFetch extends Command
{
    protected $signature = 'hybrid:scadenze-fetch';

    protected $description = 'Estrae le scadenze polizze da AssiEasy e scrive il file scadenze del giorno';

    public function handle(AssiEasyService $assiEasy): int
    {
        $log = Log::channel('cron');
        $log->info('=== hybrid:scadenze-fetch avviato ===');

        $clienti = Cliente::query()
            ->join('agenzie_new', 'agenzie_new.id', '=', 'clienti.agenziaid')
            ->where('clienti.username', '<>', '')
            ->where('clienti.cf', '<>', '')
            ->whereNotNull('agenzie_new.assiurl')->where('agenzie_new.assiurl', '<>', '')
            ->whereNotNull('agenzie_new.assisecret')->where('agenzie_new.assisecret', '<>', '')
            ->get([
                'clienti.id as cliente_id', 'clienti.username', 'clienti.cf', 'clienti.playerid',
                'agenzie_new.assiurl', 'agenzie_new.assisecret',
                'agenzie_new.os_app_id', 'agenzie_new.os_api_key',
            ]);

        $this->info('Clienti da processare: '.$clienti->count());
        $log->info('Clienti da processare: '.$clienti->count());

        $scadenze = [];
        foreach ($clienti as $c) {
            try {
                $password = $assiEasy->lookupPassword($c->assiurl, $c->assisecret, $c->username, $c->cf);
                if (! $password) {
                    throw new \RuntimeException('PWD mancante');
                }
                $token = $assiEasy->login($c->assiurl, $c->assisecret, $c->username, $password);
                if (! $token) {
                    throw new \RuntimeException('Token mancante');
                }

                $polizze = $assiEasy->polizzeVive($c->assiurl, $c->assisecret, $token);
                $titoli = $assiEasy->titoliAttivi($c->assiurl, $c->assisecret, $token);

                // Indice titoli per ID_POLIZZA → DATA_EFFETTO
                $titoliById = [];
                foreach ($titoli as $t) {
                    if (isset($t['ID_POLIZZA'])) {
                        $titoliById[(string) $t['ID_POLIZZA']] = $t['DATA_EFFETTO'] ?? null;
                    }
                }

                $utili = 0;
                foreach ($polizze as $p) {
                    $idPolizza = (string) ($p['ID_POLIZZA'] ?? '');
                    $dataEffetto = $titoliById[$idPolizza] ?? null;
                    if ($idPolizza === '' || ! $dataEffetto) {
                        continue;
                    }
                    $scadenze[] = [
                        'cliente_id' => (int) $c->cliente_id,
                        'id_polizza' => $idPolizza,
                        'data_effetto_titolo' => $dataEffetto,
                        'os_app_id' => $c->os_app_id,
                        'os_api_key' => $c->os_api_key,
                        'playerid' => $c->playerid,
                    ];
                    $utili++;
                }
                $log->info("Cliente {$c->cliente_id} → $utili polizze utili");
            } catch (\Throwable $e) {
                $log->warning("Cliente {$c->cliente_id} errore: ".$e->getMessage());
            }
        }

        $dir = storage_path('app/scadenze');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/scadenze_'.date('Y-m-d').'.json';
        File::put($file, (string) json_encode($scadenze, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $log->info('Scadenze salvate → '.$file.' ('.count($scadenze).' record)');
        $this->info('Scadenze salvate → '.$file.' ('.count($scadenze).' record)');

        return self::SUCCESS;
    }
}
