<?php

namespace Tests\Feature\Web;

use App\Models\AgenziaNew;
use App\Models\AgenziaSpeciale;
use App\Models\Consulenza;
use App\Models\Utente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiV2\V2TestCase;

/**
 * Tab "Speciale" del form agenzia (creazione + modifica).
 * Il caso che conta davvero è lo SPEGNIMENTO del flag: una checkbox non
 * spuntata non viene inviata dal browser, quindi un salvataggio ingenuo
 * lascerebbe la feature accesa per sempre.
 */
class AgencySpecialeTest extends V2TestCase
{
    private function host(string $path): string
    {
        return 'http://'.config('hybrid.domain_main').'/'.ltrim($path, '/');
    }

    private function admin(): Utente
    {
        return Utente::create([
            'nomeutente' => 'amministrazione',
            'password' => Hash::make('AdminPass!1'),
            'email' => 'admin@test.it',
            'authorized' => '1',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['hybrid.agency_assets_path' => storage_path('app/test-agency-assets')]);
        File::deleteDirectory(storage_path('app/test-agency-assets'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-agency-assets'));
        parent::tearDown();
    }

    // ─── Rendering ───────────────────────────────────────────────────────

    public function test_edit_page_shows_speciale_tab(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency();

        $this->actingAs($admin, 'admin')
            ->get($this->host('agenzia/'.$agenzia->id))
            ->assertOk()
            ->assertSee('Speciale')
            ->assertSee('consulenza_attiva')
            ->assertSee('speciale_form', false);
    }

    public function test_create_page_shows_speciale_tab_without_row(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get($this->host('agenzia/nuova'))
            ->assertOk()
            ->assertSee('consulenza_titolo');
    }

    // ─── Salvataggio ─────────────────────────────────────────────────────

    public function test_update_enables_feature_and_creates_row(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency();

        $this->actingAs($admin, 'admin')->post($this->host('agenzia/'.$agenzia->id), [
            'nome_agenzia' => $agenzia->nome_agenzia,
            'speciale_form' => '1',
            'consulenza_attiva' => '1',
            'consulenza_titolo' => 'Richiedi una Consulenza',
            'consulenza_testo' => 'Prenota un appuntamento',
            'consulenza_mail' => 'consulenze@test.it',
        ])->assertRedirect($this->host('agenzia/'.$agenzia->id));

        $speciale = AgenziaSpeciale::find($agenzia->id);
        $this->assertNotNull($speciale);
        $this->assertTrue($speciale->consulenza_attiva);
        $this->assertSame('Richiedi una Consulenza', $speciale->consulenza_titolo);
        $this->assertSame('consulenze@test.it', $speciale->consulenza_mail);
    }

    /** LA trappola: senza la checkbox nel POST il flag deve andare a false. */
    public function test_update_without_checkbox_disables_feature(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency();
        AgenziaSpeciale::create([
            'id_agenzia' => $agenzia->id,
            'consulenza_attiva' => true,
            'consulenza_titolo' => 'Richiedi una Consulenza',
        ]);

        $this->actingAs($admin, 'admin')->post($this->host('agenzia/'.$agenzia->id), [
            'nome_agenzia' => $agenzia->nome_agenzia,
            'speciale_form' => '1',
            // consulenza_attiva assente = checkbox non spuntata
            'consulenza_titolo' => 'Richiedi una Consulenza',
        ])->assertRedirect();

        $speciale = AgenziaSpeciale::find($agenzia->id);
        $this->assertFalse($speciale->consulenza_attiva);
        // I testi restano: spegnere non deve cancellare la configurazione
        $this->assertSame('Richiedi una Consulenza', $speciale->consulenza_titolo);
    }

    /** Un POST che non contiene la tab (alias legacy) non deve azzerare nulla. */
    public function test_post_without_speciale_form_leaves_row_untouched(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency();
        AgenziaSpeciale::create([
            'id_agenzia' => $agenzia->id,
            'consulenza_attiva' => true,
            'consulenza_titolo' => 'Intatto',
        ]);

        $this->actingAs($admin, 'admin')->post($this->host('res/updateagenzia.php'), [
            'id' => $agenzia->id,
            'nome_agenzia' => 'Rinominata',
        ])->assertRedirect();

        $speciale = AgenziaSpeciale::find($agenzia->id);
        $this->assertTrue($speciale->consulenza_attiva);
        $this->assertSame('Intatto', $speciale->consulenza_titolo);
        $this->assertSame('Rinominata', $agenzia->fresh()->nome_agenzia);
    }

    public function test_create_agency_with_feature_enabled(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post($this->host('agenzia'), [
            'nome_app' => 'App Speciale',
            'nome_agenzia' => 'Agenzia Speciale',
            'quick_email' => 'info@speciale.it',
            'logo_agenzia' => UploadedFile::fake()->image('logo.png', 100, 100),
            'speciale_form' => '1',
            'consulenza_attiva' => '1',
            'consulenza_titolo' => 'Consulenza',
        ])->assertRedirect($this->host('home'));

        $agenzia = AgenziaNew::where('nome_agenzia', 'Agenzia Speciale')->first();
        $this->assertNotNull($agenzia);
        $this->assertTrue(AgenziaSpeciale::find($agenzia->id)->consulenza_attiva);
    }

    public function test_default_is_disabled_for_untouched_agencies(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency();

        // Salvataggio della tab senza mai spuntare nulla
        $this->actingAs($admin, 'admin')->post($this->host('agenzia/'.$agenzia->id), [
            'nome_agenzia' => $agenzia->nome_agenzia,
            'speciale_form' => '1',
        ])->assertRedirect();

        $this->assertFalse(AgenziaSpeciale::find($agenzia->id)->consulenza_attiva);
    }

    // ─── Cascata ─────────────────────────────────────────────────────────

    public function test_delete_agency_removes_special_rows(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency();
        AgenziaSpeciale::create(['id_agenzia' => $agenzia->id, 'consulenza_attiva' => true]);
        Consulenza::create([
            'id_agenzia' => $agenzia->id,
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'email' => 'mario@test.it',
            'appuntamento_il' => now()->addDay(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post($this->host('agenzia/'.$agenzia->id.'/elimina'))
            ->assertRedirect($this->host('home'));

        $this->assertNull(AgenziaSpeciale::find($agenzia->id));
        $this->assertSame(0, Consulenza::where('id_agenzia', $agenzia->id)->count());
    }
}
