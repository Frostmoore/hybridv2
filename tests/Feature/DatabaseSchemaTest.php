<?php

namespace Tests\Feature;

use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Models\Notifica;
use App\Models\NotificaGenerale;
use App\Models\Operatore;
use App\Models\Polizza;
use App\Models\Sinistro;
use App\Models\Utente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_legacy_tables_exist(): void
    {
        foreach ([
            'agenzie_new', 'clienti', 'operatori', 'utenti', 'notifiche',
            'notifiche_generali', 'sinistri', 'preventivi', 'documenti',
            'delete_requests', 'polizze', 'polizze_importate',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabella mancante: $table");
        }
    }

    public function test_clienti_has_legacy_columns(): void
    {
        foreach ([
            'username', 'password', 'email', 'telefono', 'nome', 'cognome', 'cf',
            'piva', 'datadinascita', 'agenziaid', 'playerid', 'privacyuno',
            'privacydue', 'privacytre', 'privacyquattro', 'firstlogin', 'lastlogin',
            'codiceagenzia', 'active', 'activation_token',
        ] as $col) {
            $this->assertTrue(Schema::hasColumn('clienti', $col), "Colonna clienti mancante: $col");
        }
    }

    public function test_agency_client_relationship(): void
    {
        $agenzia = AgenziaNew::create(['nome_agenzia' => 'Test', 'token' => 'abc']);
        $cliente = Cliente::create([
            'username' => 'mario', 'email' => 'm@x.it', 'agenziaid' => $agenzia->id,
            'cf' => 'ABC', 'active' => '1',
        ]);

        $this->assertSame($agenzia->id, $cliente->agenzia->id);
        $this->assertTrue($agenzia->clienti->contains($cliente));
        $this->assertSame('mario', $agenzia->clienti->first()->username);
    }

    public function test_models_have_no_timestamps_like_legacy(): void
    {
        $this->assertFalse((new AgenziaNew)->timestamps);
        $this->assertFalse((new Cliente)->timestamps);
        $this->assertFalse((new Notifica)->timestamps);
        $this->assertFalse((new Operatore)->timestamps);
        $this->assertFalse((new Utente)->timestamps);
    }

    public function test_notifica_csv_helpers(): void
    {
        $n = Notifica::create([
            'titolo' => 'X',
            'destinatari' => 'mario,luigi,',   // virgola finale come nel legacy
            'letta_da' => 'mario,',
            'agenziaid' => 1,
        ]);

        $this->assertSame(['mario', 'luigi'], $n->destinatariList());
        $this->assertSame(['mario'], $n->lettaDaList());
        $this->assertTrue($n->isLettaDa('MARIO'));   // case-insensitive
        $this->assertFalse($n->isLettaDa('luigi'));
    }

    public function test_polizze_unique_constraint(): void
    {
        Polizza::create(['id_agenzia' => 1, 'cf' => 'ABC', 'n_polizza' => 'P1']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Polizza::create(['id_agenzia' => 1, 'cf' => 'XYZ', 'n_polizza' => 'P1']);
    }

    public function test_password_hidden_in_serialization(): void
    {
        $cliente = Cliente::create([
            'username' => 'mario', 'email' => 'm@x.it', 'password' => 'secret', 'active' => '1',
        ]);

        $this->assertArrayNotHasKey('password', $cliente->toArray());
    }

    public function test_sinistro_stores_mixed_date_format(): void
    {
        $s = Sinistro::create([
            'id_agenzia' => 1, 'nome_denuncia' => 'Rossi Mario',
            'data_denuncia' => '13/01/2024', 'privacy_denuncia' => 'on',
        ]);

        $this->assertSame('13/01/2024', $s->fresh()->data_denuncia);
    }

    public function test_dev_seeder_runs(): void
    {
        $this->seed(\Database\Seeders\DevSeeder::class);

        $this->assertDatabaseHas('agenzie_new', ['id' => 6, 'token' => 'GwAqon0pX']);
        $this->assertDatabaseHas('clienti', ['username' => 'mario.rossi', 'active' => '1']);
        $this->assertDatabaseHas('clienti', ['username' => 'luigi.verdi', 'active' => '0']);
        $this->assertDatabaseHas('utenti', ['nomeutente' => 'amministrazione']);
        $this->assertDatabaseHas('operatori', ['email' => 'operatore@assidim.test']);
    }
}
