<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Models\Notifica;
use App\Services\AssiEasyService;
use App\Services\OneSignalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Legge il file scadenze del giorno e, per le polizze esattamente a N giorni
 * dalla DATA_EFFETTO_TITOLO (default 15, override per agenzia), invia il push
 * OneSignal e salva la notifica in-app. Porting di cron_notifiche_scadenze.php.
 */
class ScadenzeNotifiche extends Command
{
    protected $signature = 'hybrid:scadenze-notifiche {--solo-agenzia= : Processa solo questa agenzia}';

    protected $description = 'Invia le notifiche push di scadenza polizza dal file scadenze del giorno';

    public function handle(AssiEasyService $assiEasy, OneSignalService $oneSignal): int
    {
        $log = Log::channel('cron');
        $log->info('=== hybrid:scadenze-notifiche avviato ===');

        $file = storage_path('app/scadenze/scadenze_'.date('Y-m-d').'.json');
        if (! file_exists($file)) {
            $log->warning("Nessun file $file – fine.");
            $this->warn("Nessun file scadenze per oggi ($file).");

            return self::SUCCESS;
        }

        $scadenze = json_decode((string) file_get_contents($file), true);
        if (! is_array($scadenze)) {
            $log->error('JSON scadenze corrotto.');
            $this->error('JSON scadenze corrotto.');

            return self::FAILURE;
        }

        $soloAgenzia = $this->option('solo-agenzia') !== null ? (int) $this->option('solo-agenzia') : null;
        $oggi = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Rome'));
        $defaultDays = (int) config('hybrid.scadenze_days_before');
        $override = (array) config('hybrid.scadenze_days_override');

        $push = 0;
        $skip = 0;

        foreach ($scadenze as $r) {
            $pid = (string) ($r['id_polizza'] ?? '?');
            $cid = (int) ($r['cliente_id'] ?? 0);
            $dataEff = $r['data_effetto_titolo'] ?? null;

            if (! $dataEff) {
                $skip++;

                continue;
            }

            $cliente = Cliente::find($cid);
            if ($cliente === null) {
                $log->warning("Cliente $cid non trovato per polizza $pid");
                $skip++;

                continue;
            }
            $agenziaId = (int) $cliente->agenziaid;

            if ($soloAgenzia !== null && $agenziaId !== $soloAgenzia) {
                $skip++;

                continue;
            }

            $daysBefore = (int) ($override[$agenziaId] ?? $defaultDays);
            $days = $this->daysUntil((string) $dataEff, $oggi);

            if ($days !== $daysBefore) {
                $skip++;

                continue;
            }

            if (empty($r['os_app_id']) || empty($r['os_api_key']) || empty($r['playerid'])) {
                $log->info("Skip polizza $pid [CLI:$cid] OneSignal parametri mancanti");
                $skip++;

                continue;
            }

            try {
                $det = $this->dettagliPolizza($assiEasy, $cliente, $pid);

                $titolo = 'Scadenza polizza imminente';
                $testo = 'Gentile '.$cliente->cognome.' '.$cliente->nome.', la tua polizza '
                    .$det['ramo'].($det['targa'] ? ' - '.$det['targa'] : '')
                    .' scadrà tra '.$daysBefore.' giorni. Contatta il tuo agente per il rinnovo.';

                $payload = $oneSignal->buildPayload((string) $r['os_app_id'], $titolo, $testo, [(string) $r['playerid']], null);
                [$status] = $oneSignal->send((string) $r['os_api_key'], $payload);

                if ($status === 200) {
                    $push++;
                    $log->info("Push OK polizza $pid cliente $cid");

                    // Persisti la notifica in-app SOLO se il push è andato (come il legacy)
                    Notifica::create([
                        'titolo' => 'Scadenza Polizza '.$det['ramo'].($det['targa'] ? ' - '.$det['targa'] : ''),
                        'contenuto' => 'Gentile Cliente, la sua polizza '.$det['ramo'].($det['targa'] ? ' - '.$det['targa'] : '')
                            .' è in scadenza il giorno '.date('d/m/Y', strtotime((string) $dataEff)),
                        'destinatari' => (string) $cliente->username,
                        'agenziaid' => $agenziaId,
                        'dataora' => now()->format('Y-m-d H:i:s'),
                    ]);
                } else {
                    $log->error("Push FAIL polizza $pid HTTP $status");
                }
            } catch (\Throwable $e) {
                $log->warning("Skip polizza $pid cliente $cid errore: ".$e->getMessage());
            }
        }

        $log->info("Done – push inviate: $push | saltate/filtrate: $skip");
        $this->info("Push inviate: $push | saltate/filtrate: $skip");

        return self::SUCCESS;
    }

    /** Giorni interi (con segno) da oggi alla data, a mezzanotte. */
    private function daysUntil(string $dateStr, \DateTimeImmutable $oggi): int
    {
        $d = (new \DateTimeImmutable($dateStr, new \DateTimeZone('Europe/Rome')))->setTime(0, 0);

        return (int) $oggi->diff($d)->format('%r%a');
    }

    /**
     * RAMO e TARGA della polizza via AssiEasy (come getPolizzaDettagli legacy).
     *
     * @return array{ramo: string, targa: string}
     */
    private function dettagliPolizza(AssiEasyService $assiEasy, Cliente $cliente, string $idPolizza): array
    {
        $agenzia = AgenziaNew::find((int) $cliente->agenziaid);
        if ($agenzia === null || ! $agenzia->assiurl || ! $agenzia->assisecret) {
            throw new \RuntimeException('Agenzia assente o senza AssiEasy');
        }

        $password = $assiEasy->lookupPassword($agenzia->assiurl, $agenzia->assisecret, (string) $cliente->username, (string) $cliente->cf);
        if (! $password) {
            throw new \RuntimeException('PWD mancante');
        }
        $token = $assiEasy->login($agenzia->assiurl, $agenzia->assisecret, (string) $cliente->username, $password);
        if (! $token) {
            throw new \RuntimeException('TOKEN mancante');
        }

        $polizza = $assiEasy->polizzaById($agenzia->assiurl, $agenzia->assisecret, $token, $idPolizza);
        if ($polizza === null) {
            throw new \RuntimeException('Polizza non trovata');
        }

        return [
            'ramo' => (string) ($polizza['DESC_RAMO'] ?? ''),
            'targa' => (string) ($polizza['TARGA'] ?? ''),
        ];
    }
}
