<?php

namespace Tests\Feature\Agencies;

use App\Models\Operatore;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiV2\V2TestCase;

class AgenciesPanelTest extends V2TestCase
{
    private function host(string $path): string
    {
        return 'http://'.config('hybrid.domain_agencies').'/'.ltrim($path, '/');
    }

    private function operatore(array $overrides = []): Operatore
    {
        return Operatore::create(array_merge([
            'username' => 'operatore.uno',
            'email' => 'op@test.it',
            'password' => Hash::make('OpPass!1'),
            'active' => '1',
            'agid' => null,
        ], $overrides));
    }

    // ─── Auth ────────────────────────────────────────────────────────────

    public function test_root_redirects_to_login_when_guest(): void
    {
        $this->get($this->host('/'))->assertRedirect($this->host('login.php'));
        $this->get($this->host('index.php'))->assertRedirect($this->host('login.php'));
    }

    public function test_login_page_renders(): void
    {
        $this->get($this->host('login.php'))->assertOk()->assertSee('Accedi');
    }

    public function test_api_login_contract(): void
    {
        $agency = $this->makeAgency();
        $this->operatore(['agid' => $agency->id]);

        // Credenziali errate
        $this->post($this->host('api/v1/log.php'), ['username' => 'operatore.uno', 'password' => 'no'])
            ->assertOk()
            ->assertExactJson(['success' => false, 'message' => 'Credenziali non valide.']);

        // Account disattivato
        $this->operatore(['username' => 'spento', 'email' => 'spento@test.it', 'active' => '0']);
        $this->post($this->host('api/v1/log.php'), ['username' => 'spento', 'password' => 'OpPass!1'])
            ->assertExactJson(['success' => false, 'message' => 'Account disattivato.']);

        // Login ok (per email, come supporta il legacy)
        $this->post($this->host('api/v1/log.php'), ['username' => 'op@test.it', 'password' => 'OpPass!1'])
            ->assertExactJson(['success' => true, 'message' => 'Login riuscito']);

        $this->assertAuthenticated('operatore');
    }

    public function test_guest_redirected_from_protected_pages(): void
    {
        $this->get($this->host('home.php'))->assertRedirect($this->host('login.php'));
        $this->get($this->host('utenti.php'))->assertRedirect();
    }

    public function test_session_timeout_logs_out(): void
    {
        $op = $this->operatore();

        $this->actingAs($op, 'operatore')
            ->withSession(['operatore_last_activity' => time() - 4000])
            ->get($this->host('home.php'))
            ->assertRedirect($this->host('login.php?session_expired=1'));

        $this->assertGuest('operatore');
    }

    // ─── Utenti / export ─────────────────────────────────────────────────

    public function test_utenti_scoped_to_operator_agency(): void
    {
        $agency = $this->makeAgency();
        $other = $this->makeAgency(['token' => 'other']);
        $this->makeCliente($agency, ['username' => 'mio.cliente', 'cognome' => 'Mio']);
        $this->makeCliente($other, ['username' => 'altrui.cliente', 'email' => 'x@y.it', 'cf' => 'ALTRO', 'cognome' => 'Altrui']);
        $op = $this->operatore(['agid' => $agency->id]);

        $this->actingAs($op, 'operatore')
            ->get($this->host('utenti.php'))
            ->assertOk()
            ->assertSee('mio.cliente')
            ->assertDontSee('altrui.cliente');
    }

    public function test_export_csv_format(): void
    {
        $agency = $this->makeAgency();
        $this->makeCliente($agency, [
            'privacyuno' => '1|2026-01-01 10:00:00',
            'privacydue' => '0|2026-01-01 10:00:00',
            'privacytre' => null,
        ]);
        $op = $this->operatore(['agid' => $agency->id]);

        $response = $this->actingAs($op, 'operatore')->get($this->host('export_utenti.php'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);                       // BOM
        $this->assertStringContainsString('Liberatoria;Informazione', $csv);       // intestazioni legacy
        $this->assertStringContainsString('Accettato - 2026-01-01 10:00:00', $csv);
        $this->assertStringContainsString('Rifiutato - 2026-01-01 10:00:00', $csv);
        $this->assertStringContainsString('mario.rossi', $csv);
        $this->assertStringContainsString('Attivo', $csv);
    }

    // ─── Super-admin gate ────────────────────────────────────────────────

    public function test_operators_page_requires_superadmin(): void
    {
        $op = $this->operatore();

        $this->actingAs($op, 'operatore')
            ->get($this->host('operators.php'))
            ->assertRedirect($this->host('home.php'));
    }

    public function test_operators_page_for_superadmin(): void
    {
        $this->makeAgency();
        $superadmin = $this->operatore(['username' => 'smp-webmaster', 'email' => 'sa@test.it']);

        $this->actingAs($superadmin, 'operatore')
            ->get($this->host('operators.php'))
            ->assertOk()
            ->assertSee('Gestione Operatori');
    }

    // ─── API operatori ───────────────────────────────────────────────────

    public function test_register_operator_inactive_by_default(): void
    {
        $this->post($this->host('api/v1/reg.php'), [
            'username' => 'nuovo.op', 'email' => 'nuovo@test.it', 'password' => 'Pass!1234',
        ])->assertExactJson(['success' => true, 'message' => 'Registrazione completata']);

        $op = Operatore::where('email', 'nuovo@test.it')->first();
        $this->assertSame('0', (string) $op->active);
    }

    public function test_register_duplicate_email(): void
    {
        $this->operatore();

        $this->post($this->host('api/v1/reg.php'), [
            'username' => 'x', 'email' => 'op@test.it', 'password' => 'y',
        ])->assertExactJson(['success' => false, 'message' => 'Email già in uso.']);
    }

    public function test_activate_and_assign_agency(): void
    {
        $admin = $this->operatore(['username' => 'smp-webmaster', 'email' => 'sa@test.it']);
        $target = $this->operatore(['username' => 'attivando', 'email' => 'att@test.it', 'active' => '0']);
        $agency = $this->makeAgency();

        $this->actingAs($admin, 'operatore')->call('POST', $this->host('api/v1/activate_operator.php'), [], [], [],
            ['CONTENT_TYPE' => 'application/json'], json_encode(['id' => $target->id, 'active' => 1]))
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertSame('1', (string) $target->fresh()->active);

        $this->actingAs($admin, 'operatore')->call('POST', $this->host('api/v1/update_agenzia.php'), [], [], [],
            ['CONTENT_TYPE' => 'application/json'], json_encode(['id' => $target->id, 'agid' => $agency->id]))
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertSame((int) $agency->id, (int) $target->fresh()->agid);

        // agid vuoto → NULL
        $this->actingAs($admin, 'operatore')->call('POST', $this->host('api/v1/update_agenzia.php'), [], [], [],
            ['CONTENT_TYPE' => 'application/json'], json_encode(['id' => $target->id, 'agid' => '']));
        $this->assertNull($target->fresh()->agid);
    }
}
