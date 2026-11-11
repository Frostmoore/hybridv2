<?php

namespace Tests\Feature\Web;

use App\Models\Polizza;
use App\Models\Utente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiV2\V2TestCase;

/**
 * La pagina di import sul dominio principale (`/importa-polizze`), cioe' la
 * gemella di quella del pannello agenzie con in piu' la scelta dell'agenzia.
 *
 * Quello che questi test difendono:
 *
 * - **la tendina c'e' e vale qualcosa**: senza agenzia non si importa, e un id
 *   inventato non crea polizze orfane.
 * - **l'agenzia non cambia per strada**: quella scelta al passo 1 viene salvata
 *   col file e riusata al passo 3, anche se la richiesta finale ne porta
 *   un'altra.
 * - **i due pannelli non si rubano il file**: le chiavi di sessione sono
 *   diverse di proposito.
 */
class AdminImportPolizzeTest extends V2TestCase
{
    private function host(string $path): string
    {
        return 'http://'.config('hybrid.domain_main').'/'.ltrim($path, '/');
    }

    /** L'admin del pannello principale vive nella tabella legacy `utenti`. */
    private function admin(): Utente
    {
        return Utente::create([
            'nomeutente' => 'amministrazione',
            'password' => Hash::make('AdminPass!1'),
            'email' => 'admin@test.it',
        ]);
    }

    private function csv(string $contenuto): UploadedFile
    {
        $percorso = tempnam(sys_get_temp_dir(), 'adm').'.csv';
        file_put_contents($percorso, $contenuto);

        return new UploadedFile($percorso, 'polizze.csv', 'text/csv', null, true);
    }

    private function csvDiEsempio(): UploadedFile
    {
        return $this->csv(
            "Tessera sanitaria;Contratto nr.;Chi firma\n"
            ."RSSMRA80A01H501U;POL-001;Mario Rossi\n"
            ."VRDLGI75B02H501X;POL-002;Luigi Verdi\n"
        );
    }

    // ─── accesso ─────────────────────────────────────────────────────────

    public function test_ospite_non_entra(): void
    {
        $this->get($this->host('importa-polizze'))->assertRedirect();
        $this->post($this->host('importa-polizze/carica'))->assertRedirect();
        $this->assertSame(0, Polizza::count());
    }

    // ─── la tendina delle agenzie ────────────────────────────────────────

    public function test_la_pagina_elenca_le_agenzie_con_quante_polizze_hanno(): void
    {
        $piena = $this->makeAgency(['nome_agenzia' => 'Agenzia Piena', 'token' => 't1', 'token_interno' => 'i1']);
        $this->makeAgency(['nome_agenzia' => 'Agenzia Vuota', 'token' => 't2', 'token_interno' => 'i2']);
        Polizza::create(['id_agenzia' => $piena->id, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'A1']);

        $this->actingAs($this->admin(), 'admin')
            ->get($this->host('importa-polizze'))
            ->assertOk()
            ->assertSee('Agenzia di destinazione')
            ->assertSee('Agenzia Piena — 1 polizze')
            ->assertSee('Agenzia Vuota — nessuna polizza')
            ->assertSee('Carica un file');
    }

    public function test_senza_agenzia_non_si_importa(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post($this->host('importa-polizze/carica'), ['file' => $this->csvDiEsempio()])
            ->assertSessionHas('errore', fn (string $m): bool => str_contains($m, 'Scegli l\'agenzia'));

        $this->assertSame(0, Polizza::count());
    }

    public function test_un_agenzia_inesistente_viene_rifiutata(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post($this->host('importa-polizze/carica'), [
                'file' => $this->csvDiEsempio(),
                'agenzia_id' => 999999,
            ])
            ->assertSessionHas('errore', fn (string $m): bool => str_contains($m, 'Scegli l\'agenzia'));

        $this->assertSame(0, Polizza::count());
    }

    // ─── il giro completo ────────────────────────────────────────────────

    public function test_import_nell_agenzia_scelta(): void
    {
        $scelta = $this->makeAgency(['nome_agenzia' => 'Agenzia Scelta', 'token' => 't1', 'token_interno' => 'i1']);
        $altra = $this->makeAgency(['nome_agenzia' => 'Agenzia Altra', 'token' => 't2', 'token_interno' => 'i2']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post($this->host('importa-polizze/carica'), [
            'file' => $this->csvDiEsempio(),
            'agenzia_id' => $scelta->id,
        ])->assertRedirect($this->host('importa-polizze/mappatura'));

        // Al passo 2 si vede verso quale agenzia sta andando.
        $this->actingAs($admin, 'admin')
            ->get($this->host('importa-polizze/mappatura'))
            ->assertOk()
            ->assertSee('Agenzia Scelta')
            ->assertSee('Tessera sanitaria')
            ->assertSee('RSSMRA80A01H501U');

        $this->actingAs($admin, 'admin')->post($this->host('importa-polizze/importa'), [
            'colonna' => [0 => 'cf', 1 => 'n_polizza', 2 => 'contraente'],
            'intestazione' => '1',
        ])->assertRedirect($this->host('importa-polizze'));

        $this->assertSame(2, Polizza::where('id_agenzia', $scelta->id)->count());
        $this->assertSame(0, Polizza::where('id_agenzia', $altra->id)->count());
        $this->assertSame('Mario Rossi', Polizza::where('n_polizza', 'POL-001')->value('contraente'));
        $this->assertSame('Agenzia Scelta', session('esito')['agenzia']);
    }

    public function test_l_agenzia_non_si_puo_cambiare_fra_i_due_passi(): void
    {
        $scelta = $this->makeAgency(['nome_agenzia' => 'Scelta', 'token' => 't1', 'token_interno' => 'i1']);
        $altra = $this->makeAgency(['nome_agenzia' => 'Altra', 'token' => 't2', 'token_interno' => 'i2']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post($this->host('importa-polizze/carica'), [
            'file' => $this->csvDiEsempio(),
            'agenzia_id' => $scelta->id,
        ]);

        // La richiesta finale prova a dirottare l'import: deve essere ignorata,
        // perche' l'id usato e' quello salvato insieme al file.
        $this->actingAs($admin, 'admin')->post($this->host('importa-polizze/importa'), [
            'colonna' => [0 => 'cf', 1 => 'n_polizza'],
            'intestazione' => '1',
            'agenzia_id' => $altra->id,
        ]);

        $this->assertSame(2, Polizza::where('id_agenzia', $scelta->id)->count());
        $this->assertSame(0, Polizza::where('id_agenzia', $altra->id)->count());
    }

    public function test_sostituisci_tocca_solo_l_agenzia_scelta(): void
    {
        $scelta = $this->makeAgency(['nome_agenzia' => 'Scelta', 'token' => 't1', 'token_interno' => 'i1']);
        $altra = $this->makeAgency(['nome_agenzia' => 'Altra', 'token' => 't2', 'token_interno' => 'i2']);
        Polizza::create(['id_agenzia' => $scelta->id, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'VECCHIA-A']);
        Polizza::create(['id_agenzia' => $altra->id, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'VECCHIA-B']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post($this->host('importa-polizze/carica'), [
            'file' => $this->csvDiEsempio(),
            'agenzia_id' => $scelta->id,
            'modalita' => 'sostituisci',
        ]);
        $this->actingAs($admin, 'admin')->post($this->host('importa-polizze/importa'), [
            'colonna' => [0 => 'cf', 1 => 'n_polizza'],
            'intestazione' => '1',
        ]);

        $this->assertDatabaseMissing('polizze', ['n_polizza' => 'VECCHIA-A']);
        $this->assertDatabaseHas('polizze', ['n_polizza' => 'VECCHIA-B']);
    }

    public function test_annulla_butta_il_file(): void
    {
        $ag = $this->makeAgency(['nome_agenzia' => 'Scelta', 'token' => 't1', 'token_interno' => 'i1']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post($this->host('importa-polizze/carica'), [
            'file' => $this->csvDiEsempio(),
            'agenzia_id' => $ag->id,
        ]);
        $this->actingAs($admin, 'admin')->post($this->host('importa-polizze/annulla'))
            ->assertRedirect($this->host('importa-polizze'));

        $this->actingAs($admin, 'admin')->get($this->host('importa-polizze/mappatura'))
            ->assertRedirect($this->host('importa-polizze'));
        $this->assertSame(0, Polizza::count());
    }

    public function test_il_vecchio_wizard_e_ancora_raggiungibile_al_suo_nuovo_url(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get($this->host('importa-polizze-vecchio'))
            ->assertOk();
    }
}
