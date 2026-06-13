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

    protected function setUp(): void
    {
        parent::setUp();
        // Path isolato: i test NON devono toccare le immagini reali di sviluppo
        config(['hybrid.agency_assets_path' => storage_path('app/test-agency-assets')]);
        File::deleteDirectory(storage_path('app/test-agency-assets'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-agency-assets'));
        parent::tearDown();
    }

    private function assetPath(string $rel): string
    {
        return config('hybrid.agency_assets_path').'/'.$rel;
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

    // ─── Notifiche: broadcast globale ────────────────────────────────────

    public function test_notifiche_broadcast_to_all_users(): void
    {
        \Illuminate\Support\Facades\Http::fake(['api.onesignal.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'ok'], 200)]);
        $admin = $this->admin();

        // Agenzia A con OneSignal + un utente; Agenzia B senza OneSignal + un utente
        $a = $this->makeAgency(['token' => 'a', 'os_app_id' => 'app-a', 'os_api_key' => 'key-a']);
        $b = $this->makeAgency(['token' => 'b', 'os_app_id' => '', 'os_api_key' => '']);
        $this->makeCliente($a, ['username' => 'utente.a', 'email' => 'a@x.it', 'cf' => 'CFA']);
        $this->makeCliente($b, ['username' => 'utente.b', 'email' => 'b@x.it', 'cf' => 'CFB']);

        $this->actingAs($admin, 'admin')
            ->from($this->host('notifiche'))
            ->post($this->host('notifiche'), [
                'notificationTitle' => 'Manutenzione', 'notificationText' => 'Domani alle 3.',
            ])
            ->assertRedirect($this->host('notifiche'));

        // In-app + banner per ENTRAMBE le agenzie
        $this->assertSame(2, \App\Models\Notifica::count());
        $this->assertSame(2, \App\Models\NotificaGenerale::count());
        $this->assertSame('utente.a', \App\Models\Notifica::where('agenziaid', $a->id)->first()->destinatari);

        // Push solo per l'agenzia con OneSignal configurato
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), 'onesignal'));
    }

    public function test_notifiche_broadcast_requires_title_and_text(): void
    {
        $admin = $this->admin();
        $this->makeAgency();

        $this->actingAs($admin, 'admin')
            ->from($this->host('notifiche'))
            ->post($this->host('notifiche'), ['notificationTitle' => '  ', 'notificationText' => ''])
            ->assertRedirect($this->host('notifiche'));

        $this->assertSame(0, \App\Models\NotificaGenerale::count());
    }

    public function test_notifiche_broadcast_requires_admin(): void
    {
        $this->post($this->host('notifiche'), ['notificationTitle' => 'x', 'notificationText' => 'y'])
            ->assertRedirect($this->host('login'));
        $this->assertSame(0, \App\Models\NotificaGenerale::count());
    }

    // ─── Utenti (cross-agenzia) ──────────────────────────────────────────

    public function test_utenti_requires_admin(): void
    {
        $this->get($this->host('utenti'))->assertRedirect($this->host('login'));
    }

    public function test_utenti_lists_all_agencies_and_searches(): void
    {
        $admin = $this->admin();
        $a = $this->makeAgency(['nome_agenzia' => 'Agenzia Uno', 'token' => 'a']);
        $b = $this->makeAgency(['nome_agenzia' => 'Agenzia Due', 'token' => 'b']);
        $this->makeCliente($a, ['username' => 'mario.rossi', 'email' => 'mario@x.it', 'cf' => 'CFA']);
        $this->makeCliente($b, ['username' => 'luigi.verdi', 'email' => 'luigi@x.it', 'cf' => 'CFB']);

        // Elenco: entrambi gli utenti e i nomi agenzia
        $this->actingAs($admin, 'admin')->get($this->host('utenti'))
            ->assertOk()
            ->assertSee('mario.rossi')->assertSee('luigi.verdi')
            ->assertSee('Agenzia Uno')->assertSee('Agenzia Due');

        // Ricerca server-side
        $this->actingAs($admin, 'admin')->get($this->host('utenti?q=luigi'))
            ->assertOk()
            ->assertSee('luigi.verdi')
            ->assertDontSee('mario.rossi');
    }

    public function test_utente_attiva(): void
    {
        $admin = $this->admin();
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency, ['active' => '0']);

        $this->actingAs($admin, 'admin')
            ->from($this->host('utenti'))
            ->post($this->host('utenti/'.$cliente->id.'/attiva'))
            ->assertRedirect($this->host('utenti'));

        $this->assertSame('1', (string) $cliente->fresh()->active);
    }

    public function test_utente_send_reset_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $admin = $this->admin();
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency, ['email' => 'reset@test.it', 'activation_token' => 'vecchio']);

        $this->actingAs($admin, 'admin')
            ->from($this->host('utenti'))
            ->post($this->host('utenti/'.$cliente->id.'/reset-password'))
            ->assertRedirect($this->host('utenti'));

        // Token rigenerato (link precedenti invalidati)
        $newToken = $cliente->fresh()->activation_token;
        $this->assertNotSame('vecchio', $newToken);
        $this->assertNotEmpty($newToken);

        // Email col link cambiapassword e il token nuovo
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\LegacyHtmlMail::class, function ($m) use ($cliente, $newToken) {
            return $m->hasTo('reset@test.it')
                && str_contains($m->htmlBody, 'cambiapassword.php?a='.$cliente->id)
                && str_contains($m->htmlBody, 'b='.$newToken);
        });
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
        $this->assertFileExists($this->assetPath('img/'.$agenzia->id.'/logo_agenzia.png'));
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
        // La testata viene auto-compressa in JPEG (contenuto), pur restando .png
        $path = $this->assetPath('img/'.$agenzia->id.'/header_agenzia.png');
        $this->assertFileExists($path);
        $this->assertSame(IMAGETYPE_JPEG, getimagesize($path)[2]);
        // Il token NON cambia in update
        $this->assertSame('agency-token-123', $fresh->token);
    }

    public function test_header_upload_is_downscaled_and_compressed(): void
    {
        $admin = $this->admin();
        $agenzia = $this->makeAgency();

        // Testata "grande" 2000px → deve scendere a max 1200 ed essere JPEG
        $this->actingAs($admin, 'admin')->post($this->host('agenzia/'.$agenzia->id), [
            'header_agenzia' => UploadedFile::fake()->image('header.png', 2000, 1000),
        ])->assertRedirect($this->host('agenzia/'.$agenzia->id));

        $path = $this->assetPath('img/'.$agenzia->id.'/header_agenzia.png');
        $info = getimagesize($path);
        $this->assertLessThanOrEqual(1200, $info[0]);   // larghezza ridotta
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);     // ricompressa JPEG
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
