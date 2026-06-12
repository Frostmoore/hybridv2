<?php

namespace Tests\Feature\Web;

use App\Models\Polizza;
use App\Models\PolizzaImportata;
use App\Models\Utente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiV2\V2TestCase;

class ImportPolizzeTest extends V2TestCase
{
    private function host(string $path): string
    {
        return 'http://'.config('hybrid.domain_main').'/'.ltrim($path, '/');
    }

    private function csvUpload(string $content, string $name = 'polizze.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    // ═══ Sistema B: import_polizze.php (password gate → tabella polizze) ═══

    public function test_system_b_requires_password(): void
    {
        config(['hybrid.import_password' => 'SegretaImport!']);

        $this->get($this->host('import_polizze.php'))->assertOk()->assertSee('Password');

        $this->post($this->host('import_polizze.php'), ['pw' => 'sbagliata'])
            ->assertOk()->assertSee('Password non corretta.');

        $this->post($this->host('import_polizze.php'), ['pw' => 'SegretaImport!'])
            ->assertRedirect();

        $this->get($this->host('import_polizze.php'))->assertOk()->assertSee('Token interni');
    }

    public function test_system_b_upsert_import(): void
    {
        config(['hybrid.import_password' => 'pw']);
        $agency = $this->makeAgency();
        $this->post($this->host('import_polizze.php'), ['pw' => 'pw']);

        $csv = "CF;N.POLIZZA;COMPAGNIA;DATA DECORRENZA\n"
            ."rssmra80a01h501z;P001;Generali;01/01/2026\n"
            ."VRDLGU85B02H501Y;P002;Allianz;45658\n"   // 45658 = seriale Excel (1/1/2025)
            .";SENZACF;X;\n";                            // riga senza CF → saltata

        $this->post($this->host('import_polizze.php'), [
            'agency_id' => $agency->id,
            'mode' => 'upsert',
            'import_file' => $this->csvUpload($csv),
        ])->assertOk()->assertSee('2 righe importate')->assertSee('1 saltate');

        $p1 = Polizza::where('n_polizza', 'P001')->first();
        $this->assertSame('RSSMRA80A01H501Z', $p1->cf);            // CF uppercased
        $this->assertSame('01/01/2026', $p1->data_decorrenza);

        // Seriale Excel convertito in gg/mm/aaaa
        $p2 = Polizza::where('n_polizza', 'P002')->first();
        $this->assertSame('01/01/2025', $p2->data_decorrenza);

        // Upsert: re-import della stessa polizza aggiorna invece di duplicare
        $csv2 = "CF;N.POLIZZA;COMPAGNIA\nRSSMRA80A01H501Z;P001;Unipol\n";
        $this->post($this->host('import_polizze.php'), [
            'agency_id' => $agency->id,
            'mode' => 'upsert',
            'import_file' => $this->csvUpload($csv2),
        ]);
        $this->assertSame(1, Polizza::where('n_polizza', 'P001')->count());
        $this->assertSame('Unipol', Polizza::where('n_polizza', 'P001')->first()->compagnia);
    }

    public function test_system_b_replace_mode(): void
    {
        config(['hybrid.import_password' => 'pw']);
        $agency = $this->makeAgency();
        Polizza::create(['id_agenzia' => $agency->id, 'cf' => 'VECCHIO', 'n_polizza' => 'OLD1']);
        Polizza::create(['id_agenzia' => 999, 'cf' => 'ALTRA', 'n_polizza' => 'KEEP']);
        $this->post($this->host('import_polizze.php'), ['pw' => 'pw']);

        $this->post($this->host('import_polizze.php'), [
            'agency_id' => $agency->id,
            'mode' => 'replace',
            'import_file' => $this->csvUpload("CF;N.POLIZZA\nNUOVO;NEW1\n"),
        ]);

        $this->assertNull(Polizza::where('n_polizza', 'OLD1')->first());      // sostituita
        $this->assertNotNull(Polizza::where('n_polizza', 'KEEP')->first());   // altra agenzia intatta
        $this->assertNotNull(Polizza::where('n_polizza', 'NEW1')->first());
    }

    public function test_system_b_missing_cf_column(): void
    {
        config(['hybrid.import_password' => 'pw']);
        $agency = $this->makeAgency();
        $this->post($this->host('import_polizze.php'), ['pw' => 'pw']);

        $this->post($this->host('import_polizze.php'), [
            'agency_id' => $agency->id,
            'mode' => 'upsert',
            'import_file' => $this->csvUpload("NOME;N.POLIZZA\nx;P1\n"),
        ])->assertSee('Colonna CF non trovata');
    }

    // ═══ Sistema A: importa_polizze.php (wizard admin → polizze_importate) ═══

    private function adminSession(): Utente
    {
        return Utente::create([
            'nomeutente' => 'amministrazione',
            'password' => Hash::make('x'),
            'email' => 'a@b.it',
        ]);
    }

    public function test_system_a_requires_admin(): void
    {
        $this->get($this->host('importa_polizze.php'))->assertRedirect();
    }

    public function test_system_a_full_wizard_flow(): void
    {
        $admin = $this->adminSession();
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);   // per il match cliente_id via CF

        // Step 1: upload
        $csv = "codice_fiscale;num_polizza;compagnia;scadenza;premio\n"
            ."RSSMRA80A01H501Z;A100;Generali;31/12/2026;1.234,56\n"
            ."SCONOSCIUTO;B200;Allianz;2026-06-30;100\n";

        $response = $this->actingAs($admin, 'admin')->post($this->host('importa_polizze.php'), [
            'step' => '1',
            'id_agenzia' => $agency->id,
            'delimiter' => ';',
            'encoding' => 'UTF-8',
            'on_duplicate' => 'update',
            'polizze_file' => $this->csvUpload($csv),
        ]);

        // Step 2 con mapping auto-indovinato
        $response->assertOk()->assertSee('Step 2')->assertSee('codice_fiscale');

        // Process (AJAX)
        $this->actingAs($admin, 'admin')->postJson($this->host('res/import_process.php'), [
            'mapping' => [
                'cf' => 'codice_fiscale',
                'numero_polizza' => 'num_polizza',
                'compagnia' => 'compagnia',
                'data_scadenza' => 'scadenza',
                'premio' => 'premio',
            ],
            'datefmt' => ['data_scadenza' => 'auto'],
        ])->assertOk()
            ->assertJsonPath('inseriti', 2)
            ->assertJsonPath('errori', 0);

        $a = PolizzaImportata::where('numero_polizza', 'A100')->first();
        $this->assertSame((int) $cliente->id, (int) $a->cliente_id);         // match per CF
        $this->assertSame('2026-12-31', $a->data_effetto_titolo);            // fallback da data_scadenza
        $this->assertSame('1234.56', (string) $a->premio);                   // premio normalizzato
        $this->assertSame('RSSMRA80A01H501Z', $a->cf);

        $b = PolizzaImportata::where('numero_polizza', 'B200')->first();
        $this->assertNull($b->cliente_id);                                   // CF senza cliente
        $this->assertSame('2026-06-30', $b->data_scadenza);
    }

    public function test_system_a_duplicate_handling(): void
    {
        $admin = $this->adminSession();
        $agency = $this->makeAgency();
        PolizzaImportata::create(['id_agenzia' => $agency->id, 'numero_polizza' => 'DUP1', 'cf' => 'VECCHIO', 'compagnia' => 'Vecchia']);

        $csv = "cf;polizza;compagnia\nNUOVOCF;DUP1;Nuova\n";

        // onDup = skip (default)
        $this->actingAs($admin, 'admin')->post($this->host('importa_polizze.php'), [
            'step' => '1', 'id_agenzia' => $agency->id, 'delimiter' => ';',
            'on_duplicate' => 'skip',
            'polizze_file' => $this->csvUpload($csv),
        ]);
        $this->actingAs($admin, 'admin')->postJson($this->host('res/import_process.php'), [
            'mapping' => ['cf' => 'cf', 'numero_polizza' => 'polizza', 'compagnia' => 'compagnia'],
            'datefmt' => [],
        ])->assertJsonPath('saltati', 1);
        $this->assertSame('Vecchia', PolizzaImportata::where('numero_polizza', 'DUP1')->first()->compagnia);

        // onDup = update
        $this->actingAs($admin, 'admin')->post($this->host('importa_polizze.php'), [
            'step' => '1', 'id_agenzia' => $agency->id, 'delimiter' => ';',
            'on_duplicate' => 'update',
            'polizze_file' => $this->csvUpload($csv),
        ]);
        $this->actingAs($admin, 'admin')->postJson($this->host('res/import_process.php'), [
            'mapping' => ['cf' => 'cf', 'numero_polizza' => 'polizza', 'compagnia' => 'compagnia'],
            'datefmt' => [],
        ])->assertJsonPath('aggiornati', 1);
        $this->assertSame('Nuova', PolizzaImportata::where('numero_polizza', 'DUP1')->first()->compagnia);
        $this->assertSame(1, PolizzaImportata::where('numero_polizza', 'DUP1')->count());
    }
}
