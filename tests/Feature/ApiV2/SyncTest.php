<?php

namespace Tests\Feature\ApiV2;

use App\Models\Cliente;
use App\Models\Operatore;
use Illuminate\Support\Facades\DB;

class SyncTest extends V2TestCase
{
    private const URL = '/res/api/v2/sync.php';

    protected function setUp(): void
    {
        parent::setUp();
        config(['hybrid.sync_secret' => 'super-secret']);
    }

    private function headers(string $secret = 'super-secret'): array
    {
        return ['X-Sync-Secret' => $secret];
    }

    public function test_requires_valid_secret(): void
    {
        $this->postJson(self::URL, ['entity' => 'cliente', 'data' => ['id' => 5]], $this->headers('sbagliato'))
            ->assertStatus(401);
        $this->postJson(self::URL, ['entity' => 'cliente', 'data' => ['id' => 5]])
            ->assertStatus(401);
    }

    public function test_unknown_entity_422(): void
    {
        $this->postJson(self::URL, ['entity' => 'pippo', 'data' => ['id' => 1]], $this->headers())
            ->assertStatus(422);
    }

    public function test_missing_id_422(): void
    {
        $this->postJson(self::URL, ['entity' => 'cliente', 'data' => ['username' => 'x']], $this->headers())
            ->assertStatus(422);
    }

    public function test_upserts_cliente_by_id(): void
    {
        $agency = $this->makeAgency(['versione_app' => 'v1']);

        // INSERT con id esplicito (id "vecchio" < 1M)
        $this->postJson(self::URL, ['entity' => 'cliente', 'data' => [
            'id' => 700, 'username' => 'mario.vecchio', 'email' => 'mario@old.it',
            'agenziaid' => $agency->id, 'nome' => 'Mario', 'cognome' => 'Rossi',
            'campo_inesistente' => 'ignorato',   // colonna non esistente → scartata
        ]], $this->headers())->assertOk()->assertJsonPath('data.synced', true);

        $c = Cliente::find(700);
        $this->assertNotNull($c);
        $this->assertSame('mario.vecchio', $c->username);
        $this->assertSame((int) $agency->id, (int) $c->agenziaid);

        // UPDATE stesso id → idempotente, aggiorna
        $this->postJson(self::URL, ['entity' => 'cliente', 'data' => [
            'id' => 700, 'username' => 'mario.vecchio', 'email' => 'nuova@old.it', 'agenziaid' => $agency->id,
        ]], $this->headers())->assertOk();

        $this->assertSame('nuova@old.it', Cliente::find(700)->email);
        $this->assertSame(1, Cliente::where('id', 700)->count());   // no doppioni
    }

    public function test_skips_upsert_when_agency_is_v2(): void
    {
        $agency = $this->makeAgency();
        DB::table('agenzie_new')->where('id', $agency->id)->update(['versione_app' => 'v2']);

        // cliente: scartato (il nuovo è autorevole), ma risposta 200 (no retry lato vecchio)
        $this->postJson(self::URL, ['entity' => 'cliente', 'data' => [
            'id' => 701, 'username' => 'tardo.vecchio', 'agenziaid' => $agency->id,
        ]], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.synced', false)
            ->assertJsonPath('data.skipped', 'agency_v2');
        $this->assertNull(Cliente::find(701));

        // anche l'agenzia stessa non va sovrascritta quando è v2
        $this->postJson(self::URL, ['entity' => 'agenzia', 'data' => [
            'id' => $agency->id, 'nome_agenzia' => 'TENTATIVO CLOBBER',
        ]], $this->headers())->assertOk()->assertJsonPath('data.synced', false);
        $this->assertNotSame('TENTATIVO CLOBBER', DB::table('agenzie_new')->where('id', $agency->id)->value('nome_agenzia'));
    }

    public function test_syncs_when_agency_is_v1(): void
    {
        $agency = $this->makeAgency(['versione_app' => 'v1']);
        $this->postJson(self::URL, ['entity' => 'cliente', 'data' => [
            'id' => 702, 'username' => 'attivo.vecchio', 'agenziaid' => $agency->id,
        ]], $this->headers())->assertOk()->assertJsonPath('data.synced', true);
        $this->assertNotNull(Cliente::find(702));
    }

    public function test_upserts_operatore(): void
    {
        $agency = $this->makeAgency();
        $this->postJson(self::URL, ['entity' => 'operatore', 'data' => [
            'id' => 800, 'username' => 'op.vecchio', 'agid' => $agency->id,
        ]], $this->headers())->assertOk();

        $this->assertSame('op.vecchio', Operatore::find(800)->username);
    }
}
