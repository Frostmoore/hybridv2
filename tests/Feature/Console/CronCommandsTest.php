<?php

namespace Tests\Feature\Console;

use App\Models\Notifica;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Feature\ApiV2\V2TestCase;

class CronCommandsTest extends V2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(storage_path('app/backups'));
        File::deleteDirectory(storage_path('app/scadenze'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/backups'));
        File::deleteDirectory(storage_path('app/scadenze'));
        parent::tearDown();
    }

    // ─── hybrid:backup-db ────────────────────────────────────────────────

    public function test_backup_creates_json_dump_with_retention(): void
    {
        $agency = $this->makeAgency();
        $this->makeCliente($agency);

        // 8 vecchi backup → dopo il run ne restano 7 in tutto
        File::ensureDirectoryExists(storage_path('app/backups'));
        foreach (range(1, 8) as $i) {
            $f = storage_path("app/backups/backup_2020-01-0$i.json");
            File::put($f, '{}');
            touch($f, strtotime("2020-01-0$i"));
        }

        $this->artisan('hybrid:backup-db')->assertSuccessful();

        $file = storage_path('app/backups/backup_'.date('Y-m-d').'.json');
        $this->assertFileExists($file);

        $dump = json_decode((string) file_get_contents($file), true);
        $this->assertArrayHasKey('clienti', $dump);
        $this->assertArrayHasKey('agenzie_new', $dump);
        $this->assertCount(1, $dump['clienti']);
        $this->assertSame('mario.rossi', $dump['clienti'][0]['username']);

        $this->assertCount(7, File::glob(storage_path('app/backups/backup_*.json')));
    }

    // ─── hybrid:scadenze-fetch ───────────────────────────────────────────

    public function test_fetch_writes_scadenze_file(): void
    {
        Http::fake([
            '*/assieasy/clienti/autenticazione/get_credenziali_utente' => Http::response(['data' => ['PASSWORD' => 'pw-ae']]),
            '*/assieasy/clienti/autenticazione/login' => Http::response(['data' => ['TOKEN' => 'tok-ae']]),
            '*/assieasy/clienti/polizze/get' => Http::response(['data' => [
                ['ID_POLIZZA' => '111', 'DESC_PRODOTTO' => 'RCA'],
                ['ID_POLIZZA' => '222', 'DESC_PRODOTTO' => 'Casa'],   // senza titolo → esclusa
            ]]),
            '*/assieasy/clienti/titoli/get' => Http::response(['data' => [
                ['ID_POLIZZA' => '111', 'DATA_EFFETTO' => '2026-12-31'],
            ]]),
        ]);

        $agency = $this->makeAgency([
            'assiurl' => 'test.assieasy.com', 'assisecret' => 'secret',
            'os_app_id' => 'os-app', 'os_api_key' => 'os-key',
        ]);
        $cliente = $this->makeCliente($agency, ['playerid' => 'player-1']);
        // Cliente senza CF → escluso dalla query
        $this->makeCliente($agency, ['username' => 'senza.cf', 'email' => 'no@cf.it', 'cf' => '']);

        $this->artisan('hybrid:scadenze-fetch')->assertSuccessful();

        $file = storage_path('app/scadenze/scadenze_'.date('Y-m-d').'.json');
        $this->assertFileExists($file);

        $records = json_decode((string) file_get_contents($file), true);
        $this->assertCount(1, $records);
        $this->assertSame([
            'cliente_id' => (int) $cliente->id,
            'id_polizza' => '111',
            'data_effetto_titolo' => '2026-12-31',
            'os_app_id' => 'os-app',
            'os_api_key' => 'os-key',
            'playerid' => 'player-1',
        ], $records[0]);
    }

    public function test_fetch_skips_failing_client_and_continues(): void
    {
        Http::fake([
            '*/assieasy/*' => Http::response(['data' => []]),   // PASSWORD assente → errore gestito
        ]);

        $agency = $this->makeAgency(['assiurl' => 'x.assieasy.com', 'assisecret' => 's']);
        $this->makeCliente($agency);

        $this->artisan('hybrid:scadenze-fetch')->assertSuccessful();

        $records = json_decode((string) file_get_contents(storage_path('app/scadenze/scadenze_'.date('Y-m-d').'.json')), true);
        $this->assertSame([], $records);
    }

    // ─── hybrid:scadenze-notifiche ───────────────────────────────────────

    /** Prepara il file scadenze del giorno con un record. */
    private function scadenzeFile(array $record): void
    {
        File::ensureDirectoryExists(storage_path('app/scadenze'));
        File::put(
            storage_path('app/scadenze/scadenze_'.date('Y-m-d').'.json'),
            (string) json_encode([$record]),
        );
    }

    private function fakeAssiEasyDetail(): void
    {
        Http::fake([
            '*/assieasy/clienti/autenticazione/get_credenziali_utente' => Http::response(['data' => ['PASSWORD' => 'pw']]),
            '*/assieasy/clienti/autenticazione/login' => Http::response(['data' => ['TOKEN' => 'tok']]),
            '*/assieasy/clienti/polizze/get' => Http::response(['data' => [
                ['ID_POLIZZA' => '111', 'DESC_RAMO' => 'Auto', 'TARGA' => 'AB123CD'],
            ]]),
            'api.onesignal.com/*' => Http::response(['id' => 'ok'], 200),
        ]);
    }

    public function test_notifiche_pushes_at_exact_day_and_persists(): void
    {
        $this->fakeAssiEasyDetail();
        $agency = $this->makeAgency(['assiurl' => 't.assieasy.com', 'assisecret' => 's']);
        $cliente = $this->makeCliente($agency);

        $dataEffetto = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Rome')))
            ->modify('+15 days')->format('Y-m-d');

        $this->scadenzeFile([
            'cliente_id' => (int) $cliente->id,
            'id_polizza' => '111',
            'data_effetto_titolo' => $dataEffetto,
            'os_app_id' => 'os-app', 'os_api_key' => 'os-key', 'playerid' => 'player-1',
        ]);

        $this->artisan('hybrid:scadenze-notifiche')
            ->expectsOutputToContain('Push inviate: 1')
            ->assertSuccessful();

        $n = Notifica::latest('id')->first();
        $this->assertSame('Scadenza Polizza Auto - AB123CD', $n->titolo);
        $this->assertStringContainsString('è in scadenza il giorno', $n->contenuto);
        $this->assertSame('mario.rossi', $n->destinatari);
        $this->assertSame((int) $agency->id, (int) $n->agenziaid);

        // Push mirato col playerid e testo legacy
        Http::assertSent(fn ($r) => str_contains($r->url(), 'onesignal')
            && $r['include_external_user_ids'] === ['player-1']
            && $r['headings']['en'] === 'Scadenza polizza imminente'
            && str_contains($r['contents']['en'], 'scadrà tra 15 giorni'));
    }

    public function test_notifiche_skips_beyond_window(): void
    {
        $this->fakeAssiEasyDetail();
        $agency = $this->makeAgency(['assiurl' => 't.assieasy.com', 'assisecret' => 's']);
        $cliente = $this->makeCliente($agency);

        // +20 giorni: oltre la finestra di 15 → niente push
        $dataEffetto = (new \DateTimeImmutable('today'))->modify('+20 days')->format('Y-m-d');
        $this->scadenzeFile([
            'cliente_id' => (int) $cliente->id, 'id_polizza' => '111',
            'data_effetto_titolo' => $dataEffetto,
            'os_app_id' => 'a', 'os_api_key' => 'k', 'playerid' => 'p',
        ]);

        $this->artisan('hybrid:scadenze-notifiche')
            ->expectsOutputToContain('Push inviate: 0')
            ->assertSuccessful();

        $this->assertSame(0, Notifica::count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'onesignal'));
    }

    public function test_notifiche_pushes_within_window_recovering_missed_day(): void
    {
        $this->fakeAssiEasyDetail();
        $agency = $this->makeAgency(['assiurl' => 't.assieasy.com', 'assisecret' => 's']);
        $cliente = $this->makeCliente($agency);

        // +10 giorni: dentro la finestra (il giorno 15 era stato saltato) → push,
        // col testo che riflette i giorni REALI mancanti
        $dataEffetto = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Rome')))
            ->modify('+10 days')->format('Y-m-d');
        $this->scadenzeFile([
            'cliente_id' => (int) $cliente->id, 'id_polizza' => '111',
            'data_effetto_titolo' => $dataEffetto,
            'os_app_id' => 'os-app', 'os_api_key' => 'os-key', 'playerid' => 'player-1',
        ]);

        $this->artisan('hybrid:scadenze-notifiche')
            ->expectsOutputToContain('Push inviate: 1')
            ->assertSuccessful();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'onesignal')
            && str_contains($r['contents']['en'], 'scadrà tra 10 giorni'));
        $this->assertSame(1, \App\Models\ScadenzaNotificata::count());
    }

    public function test_notifiche_dedup_skips_already_notified(): void
    {
        $this->fakeAssiEasyDetail();
        $agency = $this->makeAgency(['assiurl' => 't.assieasy.com', 'assisecret' => 's']);
        $cliente = $this->makeCliente($agency);

        $dataEffetto = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Rome')))
            ->modify('+15 days')->format('Y-m-d');
        $record = [
            'cliente_id' => (int) $cliente->id, 'id_polizza' => '111',
            'data_effetto_titolo' => $dataEffetto,
            'os_app_id' => 'os-app', 'os_api_key' => 'os-key', 'playerid' => 'player-1',
        ];

        // 1° run → push + dedup row
        $this->scadenzeFile($record);
        $this->artisan('hybrid:scadenze-notifiche')->expectsOutputToContain('Push inviate: 1')->assertSuccessful();

        // 2° run (giorno dopo, stessa scadenza ancora nel file) → niente doppione
        $this->scadenzeFile($record);
        $this->artisan('hybrid:scadenze-notifiche')->expectsOutputToContain('Push inviate: 0')->assertSuccessful();

        $this->assertSame(1, Notifica::count());
        $this->assertSame(1, \App\Models\ScadenzaNotificata::count());
    }

    public function test_notifiche_skips_v1_agencies(): void
    {
        $this->fakeAssiEasyDetail();
        // Agenzia ancora sulla vecchia app: il nuovo server NON deve notificarla
        $agency = $this->makeAgency(['assiurl' => 't.assieasy.com', 'assisecret' => 's', 'versione_app' => 'v1']);
        $cliente = $this->makeCliente($agency);

        $dataEffetto = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Rome')))
            ->modify('+15 days')->format('Y-m-d');
        $this->scadenzeFile([
            'cliente_id' => (int) $cliente->id, 'id_polizza' => '111',
            'data_effetto_titolo' => $dataEffetto,
            'os_app_id' => 'a', 'os_api_key' => 'k', 'playerid' => 'p',
        ]);

        $this->artisan('hybrid:scadenze-notifiche')
            ->expectsOutputToContain('Push inviate: 0')
            ->assertSuccessful();

        $this->assertSame(0, Notifica::count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'onesignal'));
    }

    public function test_notifiche_skips_already_expired(): void
    {
        $this->fakeAssiEasyDetail();
        $agency = $this->makeAgency(['assiurl' => 't.assieasy.com', 'assisecret' => 's']);
        $cliente = $this->makeCliente($agency);

        // -1 giorno: già scaduta → niente push (days < 0)
        $dataEffetto = (new \DateTimeImmutable('today'))->modify('-1 days')->format('Y-m-d');
        $this->scadenzeFile([
            'cliente_id' => (int) $cliente->id, 'id_polizza' => '111',
            'data_effetto_titolo' => $dataEffetto,
            'os_app_id' => 'a', 'os_api_key' => 'k', 'playerid' => 'p',
        ]);

        $this->artisan('hybrid:scadenze-notifiche')
            ->expectsOutputToContain('Push inviate: 0')
            ->assertSuccessful();

        $this->assertSame(0, Notifica::count());
    }

    public function test_notifiche_respects_agency_override(): void
    {
        $this->fakeAssiEasyDetail();
        config(['hybrid.scadenze_days_override' => []]);

        $agency = $this->makeAgency(['assiurl' => 't.assieasy.com', 'assisecret' => 's']);
        config(['hybrid.scadenze_days_override' => [(int) $agency->id => 5]]);
        $cliente = $this->makeCliente($agency);

        $dataEffetto = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Rome')))
            ->modify('+5 days')->format('Y-m-d');
        $this->scadenzeFile([
            'cliente_id' => (int) $cliente->id, 'id_polizza' => '111',
            'data_effetto_titolo' => $dataEffetto,
            'os_app_id' => 'a', 'os_api_key' => 'k', 'playerid' => 'p',
        ]);

        $this->artisan('hybrid:scadenze-notifiche')
            ->expectsOutputToContain('Push inviate: 1')
            ->assertSuccessful();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'onesignal')
            && str_contains($r['contents']['en'], 'scadrà tra 5 giorni'));
    }

    public function test_notifiche_no_insert_when_push_fails(): void
    {
        Http::fake([
            '*/assieasy/clienti/autenticazione/get_credenziali_utente' => Http::response(['data' => ['PASSWORD' => 'pw']]),
            '*/assieasy/clienti/autenticazione/login' => Http::response(['data' => ['TOKEN' => 'tok']]),
            '*/assieasy/clienti/polizze/get' => Http::response(['data' => [['ID_POLIZZA' => '111', 'DESC_RAMO' => 'Auto']]]),
            'api.onesignal.com/*' => Http::response(['errors' => ['boom']], 400),
        ]);

        $agency = $this->makeAgency(['assiurl' => 't.assieasy.com', 'assisecret' => 's']);
        $cliente = $this->makeCliente($agency);

        $dataEffetto = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Rome')))
            ->modify('+15 days')->format('Y-m-d');
        $this->scadenzeFile([
            'cliente_id' => (int) $cliente->id, 'id_polizza' => '111',
            'data_effetto_titolo' => $dataEffetto,
            'os_app_id' => 'a', 'os_api_key' => 'k', 'playerid' => 'p',
        ]);

        $this->artisan('hybrid:scadenze-notifiche')->assertSuccessful();

        $this->assertSame(0, Notifica::count());   // insert SOLO se push ok
    }

    public function test_notifiche_without_file_is_noop(): void
    {
        $this->artisan('hybrid:scadenze-notifiche')->assertSuccessful();
        $this->assertSame(0, Notifica::count());
    }

    // ─── Scheduler ───────────────────────────────────────────────────────

    public function test_scheduler_registers_legacy_times(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $byCommand = fn (string $cmd) => $events->first(fn ($e) => str_contains((string) $e->command, $cmd));

        $this->assertSame('10 14 * * *', $byCommand('hybrid:backup-db')->expression);
        $this->assertSame('10 14 * * *', $byCommand('hybrid:scadenze-fetch')->expression);
        $this->assertSame('30 14 * * *', $byCommand('hybrid:scadenze-notifiche')->expression);
        $this->assertSame('Europe/Rome', $byCommand('hybrid:scadenze-fetch')->timezone);
    }
}
