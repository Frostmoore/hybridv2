<?php

namespace Tests\Feature\Console;

use App\Models\Cliente;
use App\Models\Notifica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportLegacyExportTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = storage_path('app/test_export.ndjson');
    }

    protected function tearDown(): void
    {
        File::delete($this->file);
        parent::tearDown();
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function writeNdjson(array $lines): void
    {
        File::put($this->file, implode("\n", array_map(fn ($l) => json_encode($l, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $lines))."\n");
    }

    public function test_imports_tables_preserving_ids_and_sanitizing(): void
    {
        $this->writeNdjson([
            ['_meta' => ['exported_at' => '2026-06-12 14:00:00', 'db' => 'r55nziaa_hybrid']],
            ['_table' => ['name' => 'clienti', 'count' => 2, 'create' => 'CREATE TABLE ...']],
            ['t' => 'clienti', 'r' => [
                'id' => '42', 'username' => 'mario', 'password' => 'hash', 'email' => 'm@x.it',
                'telefono' => null, 'nome' => 'Mario', 'cognome' => 'Rossi', 'cf' => 'CF1', 'piva' => null,
                'datadinascita' => '01/01/1980', 'agenziaid' => '6', 'playerid' => null,
                'privacyuno' => '1|2026-01-01 10:00:00', 'privacydue' => null, 'privacytre' => null,
                'privacyquattro' => null, 'firstlogin' => '', 'lastlogin' => null,
                'codiceagenzia' => null, 'active' => '1', 'activation_token' => 'tok',
            ]],
            ['t' => 'clienti', 'r' => [
                'id' => '99', 'username' => 'luigi', 'password' => 'hash', 'email' => 'l@x.it',
                'telefono' => '333', 'nome' => 'Luigi', 'cognome' => 'Verdi', 'cf' => 'CF2', 'piva' => null,
                'datadinascita' => '', 'agenziaid' => '', 'playerid' => 'p9',
                'privacyuno' => null, 'privacydue' => null, 'privacytre' => null, 'privacyquattro' => null,
                'firstlogin' => '2026-01-01 10:00:00', 'lastlogin' => '2026-06-01 09:00:00',
                'codiceagenzia' => null, 'active' => null, 'activation_token' => null,
            ]],
            ['_end' => true],
        ]);

        $this->artisan('hybrid:import-legacy', ['file' => $this->file])
            ->expectsOutputToContain('✓ clienti: 2/2')
            ->assertSuccessful();

        // Id preservati
        $mario = Cliente::find(42);
        $this->assertSame('mario', $mario->username);
        // Sanificazione: '' → NULL su datetime, NULL → '' su stringhe
        $this->assertNull($mario->firstlogin);
        $this->assertSame('', $mario->telefono);
        $luigi = Cliente::find(99);
        $this->assertNull($luigi->agenziaid);    // '' su colonna intera → NULL
    }

    public function test_rewrites_old_domain_urls(): void
    {
        $this->writeNdjson([
            ['_table' => ['name' => 'notifiche', 'count' => 1, 'create' => '']],
            ['t' => 'notifiche', 'r' => [
                'id' => '1', 'titolo' => 'X', 'contenuto' => 'Vedi https://www.hybridandgogsv.it/promo',
                'immagine' => 'https://agencies.hybridandgogsv.it/uploads/notif_1.png',
                'destinatari' => 'mario,', 'letta_da' => '', 'link' => 'https://altrosito.it/pagina',
                'testolink' => null, 'dataora' => '2026-01-01 10:00:00', 'agenziaid' => '6',
            ]],
        ]);

        $this->artisan('hybrid:import-legacy', ['file' => $this->file])->assertSuccessful();

        $n = Notifica::find(1);
        $this->assertSame('https://agencies.hybridandgogsv2.it/uploads/notif_1.png', $n->immagine);
        $this->assertSame('Vedi https://www.hybridandgogsv2.it/promo', $n->contenuto);
        $this->assertSame('https://altrosito.it/pagina', $n->link);   // domini terzi intatti
    }

    public function test_no_rewrite_option(): void
    {
        $this->writeNdjson([
            ['_table' => ['name' => 'notifiche', 'count' => 1, 'create' => '']],
            ['t' => 'notifiche', 'r' => [
                'id' => '1', 'titolo' => 'X', 'contenuto' => 'c',
                'immagine' => 'https://agencies.hybridandgogsv.it/uploads/n.png',
                'destinatari' => '', 'letta_da' => '', 'link' => '', 'testolink' => null,
                'dataora' => '', 'agenziaid' => '6',
            ]],
        ]);

        $this->artisan('hybrid:import-legacy', ['file' => $this->file, '--no-rewrite' => true])->assertSuccessful();

        $this->assertSame('https://agencies.hybridandgogsv.it/uploads/n.png', Notifica::find(1)->immagine);
    }

    public function test_skips_dead_and_unknown_tables(): void
    {
        $this->writeNdjson([
            ['_table' => ['name' => 'agenzie', 'count' => 3, 'create' => '']],
            ['t' => 'agenzie', 'r' => ['id' => '1', 'nome' => 'vecchia']],
            ['_table' => ['name' => 'tabella_misteriosa', 'count' => 1, 'create' => '']],
            ['t' => 'tabella_misteriosa', 'r' => ['id' => '1']],
            ['_table' => ['name' => 'utenti', 'count' => 1, 'create' => '']],
            ['t' => 'utenti', 'r' => [
                'id' => '2', 'nomeutente' => 'amministrazione', 'password' => 'h', 'hash' => null,
                'email' => 'a@b.it', 'last_connected' => null, 'authorized' => '0', 'activation_code' => 'activated',
            ]],
        ]);

        $this->artisan('hybrid:import-legacy', ['file' => $this->file])
            ->expectsOutputToContain('agenzie: 3 righe NON importate')
            ->expectsOutputToContain('tabella_misteriosa: non esiste')
            ->expectsOutputToContain('✓ utenti: 1/1')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('utenti')->count());
    }

    public function test_rerun_is_idempotent_with_truncate(): void
    {
        $this->writeNdjson([
            ['_table' => ['name' => 'utenti', 'count' => 1, 'create' => '']],
            ['t' => 'utenti', 'r' => ['id' => '2', 'nomeutente' => 'admin', 'password' => 'h', 'hash' => null,
                'email' => 'a@b.it', 'last_connected' => null, 'authorized' => '1', 'activation_code' => null]],
        ]);

        $this->artisan('hybrid:import-legacy', ['file' => $this->file])->assertSuccessful();
        $this->artisan('hybrid:import-legacy', ['file' => $this->file])->assertSuccessful();

        $this->assertSame(1, DB::table('utenti')->count());
    }

    public function test_count_mismatch_fails(): void
    {
        $this->writeNdjson([
            ['_table' => ['name' => 'utenti', 'count' => 5, 'create' => '']],
            ['t' => 'utenti', 'r' => ['id' => '2', 'nomeutente' => 'admin', 'password' => 'h', 'hash' => null,
                'email' => 'a@b.it', 'last_connected' => null, 'authorized' => '1', 'activation_code' => null]],
        ]);

        $this->artisan('hybrid:import-legacy', ['file' => $this->file])
            ->expectsOutputToContain('⚠ utenti: 1/5')
            ->assertFailed();
    }
}
