<?php

namespace Tests\Feature\Web;

use App\Models\AgenziaNew;
use App\Models\Utente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiV2\V2TestCase;

class AdminPanelTest extends V2TestCase
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

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/agency-assets'));
        parent::tearDown();
    }

    // ─── Auth ────────────────────────────────────────────────────────────

    public function test_login_page_on_root_and_index_html(): void
    {
        $this->get($this->host('/'))->assertOk()->assertSee('Accedi');
        $this->get($this->host('login'))->assertOk()->assertSee('Accedi');
        // Il vecchio path .html redirige all'URL pulito
        $this->get($this->host('index.html'))->assertRedirect($this->host('/login'));
    }

    public function test_guest_redirected_from_admin_pages(): void
    {
        $this->get($this->host('home'))->assertRedirect();
    }

    public function test_legacy_php_urls_redirect_to_clean(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get($this->host('home.php'))->assertRedirect($this->host('/home'));
        $this->get($this->host('logout.php'))->assertRedirect($this->host('/logout'));
    }

    public function test_login_with_wrong_credentials(): void
    {
        $this->admin();

        $this->from($this->host('login'))
            ->post($this->host('login'), ['nomeutente' => 'amministrazione', 'password' => 'sbagliata'])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertGuest('admin');
    }

    public function test_login_error_renders_on_page(): void
    {
        $this->admin();

        $this->from($this->host('login'))
            ->followingRedirects()
            ->post($this->host('login'), ['nomeutente' => 'amministrazione', 'password' => 'sbagliata'])
            ->assertOk()
            ->assertSee('errati')                       // messaggio
            ->assertSee('adm-flash--err', false);       // stile moderno
    }

    public function test_login_logout_flow(): void
    {
        $this->admin();

        $this->post($this->host('login'), [
            'nomeutente' => 'amministrazione',
            'password' => 'AdminPass!1',
        ])->assertRedirect($this->host('home'));

        $this->assertAuthenticated('admin');

        $this->get($this->host('logout'))->assertRedirect();
        $this->assertGuest('admin');
    }

    public function test_legacy_authenticate_php_still_logs_in(): void
    {
        $this->admin();

        $this->post($this->host('authenticate.php'), [
            'nomeutente' => 'amministrazione',
            'password' => 'AdminPass!1',
        ])->assertRedirect($this->host('home'));

        $this->assertAuthenticated('admin');
    }

    // ─── Home ────────────────────────────────────────────────────────────

    public function test_home_lists_agencies(): void
    {
        $admin = $this->admin();
        $this->makeAgency(['nome_agenzia' => 'Agenzia Uno', 'token' => 'tok1']);

        $this->actingAs($admin, 'admin')
            ->get($this->host('home'))
            ->assertOk()
            ->assertSee('Agenzia Uno')
            ->assertSee('tok1');
    }

    // ─── Creazione agenzia ───────────────────────────────────────────────

    public function test_create_agency_requires_logo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->from($this->host('agenzia/nuova'))
            ->post($this->host('agenzia'), ['nome_agenzia' => 'Senza Logo'])
            ->assertRedirect($this->host('agenzia/nuova'));

        $this->assertSame(0, AgenziaNew::where('nome_agenzia', 'Senza Logo')->count());
    }

    public function test_create_agency_with_logo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post($this->host('agenzia'), [
            'nome_app' => 'Nuova App',
            'nome_agenzia' => 'Agenzia Nuova',
            'quick_email' => 'info@nuova.it',
            'logo_agenzia' => UploadedFile::fake()->image('logo.png', 100, 100),
        ])->assertRedirect($this->host('home'));

        $agenzia = AgenziaNew::where('nome_agenzia', 'Agenzia Nuova')->first();
        $this->assertNotNull($agenzia);
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]{10}$/', $agenzia->token);   // token legacy 10 char
        $this->assertSame('1', $agenzia->attiva);
        $this->assertSame('img/'.$agenzia->id.'/logo_agenzia.png', $agenzia->logo_agenzia);
        $this->assertFileExists(storage_path('app/agency-assets/img/'.$agenzia->id.'/logo_agenzia.png'));
    }

    public function test_create_page_renders(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get($this->host('agenzia/nuova'))
            ->assertOk()
            ->assertSee('Nuova Agenzia')
            ->assertSee('obbligatorio');   // logo richiesto
    }

    // ─── Modifica agenzia ────────────────────────────────────────────────

    public function test_edit_page_shows_fields(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency(['nome_agenzia' => 'Da Modificare']);

        $this->actingAs($admin, 'admin')
            ->get($this->host('agenzia/'.$agenzia->id))
            ->assertOk()
            ->assertSee('Da Modificare')
            ->assertSee('nome_agenzia');   // i tab/campi sono renderizzati
    }

    public function test_update_agency_text_and_image(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency();

        $this->actingAs($admin, 'admin')->post($this->host('agenzia/'.$agenzia->id), [
            'nome_agenzia' => 'Rinominata',
            'quick_telefono' => '0612345678',
            'header_agenzia' => UploadedFile::fake()->image('header.png'),
        ])->assertRedirect($this->host('agenzia/'.$agenzia->id));

        $fresh = $agenzia->fresh();
        $this->assertSame('Rinominata', $fresh->nome_agenzia);
        $this->assertSame('0612345678', $fresh->quick_telefono);
        $this->assertSame('img/'.$agenzia->id.'/header_agenzia.png', $fresh->header_agenzia);
        // Il token NON cambia in update
        $this->assertSame('agency-token-123', $fresh->token);
    }

    public function test_update_rejects_non_png_image(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency(['logo_agenzia' => 'img/x/logo_agenzia.png']);

        $this->actingAs($admin, 'admin')->post($this->host('agenzia/'.$agenzia->id), [
            'logo_agenzia' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $this->assertSame('img/x/logo_agenzia.png', $agenzia->fresh()->logo_agenzia);
    }

    // ─── Retrocompatibilità URL legacy ───────────────────────────────────

    public function test_legacy_agency_urls(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency();

        // GET vecchi → redirect ai puliti
        $this->actingAs($admin, 'admin')->get($this->host('agenzia.php?id='.$agenzia->id))
            ->assertRedirect($this->host('/agenzia/'.$agenzia->id));
        $this->actingAs($admin, 'admin')->get($this->host('creagenzia.php'))
            ->assertRedirect($this->host('/agenzia/nuova'));

        // POST legacy res/updateagenzia.php (id nel body) ancora funzionante
        $this->actingAs($admin, 'admin')->post($this->host('res/updateagenzia.php'), [
            'id' => $agenzia->id,
            'nome_agenzia' => 'Via Legacy',
        ])->assertRedirect($this->host('agenzia/'.$agenzia->id));
        $this->assertSame('Via Legacy', $agenzia->fresh()->nome_agenzia);
    }
}
