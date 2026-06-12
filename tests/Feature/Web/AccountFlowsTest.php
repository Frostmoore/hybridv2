<?php

namespace Tests\Feature\Web;

use App\Mail\LegacyHtmlMail;
use App\Models\Cliente;
use App\Models\DeleteRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\ApiV2\V2TestCase;

class AccountFlowsTest extends V2TestCase
{
    private function host(string $path): string
    {
        return 'http://'.config('hybrid.domain_main').'/'.ltrim($path, '/');
    }

    // ─── attivacliente.php ───────────────────────────────────────────────

    public function test_activation_with_valid_token(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency, ['active' => '0', 'activation_token' => str_repeat('ab', 16)]);

        $this->get($this->host('attivacliente.php?a='.$cliente->id.'&b='.str_repeat('ab', 16)))
            ->assertOk()
            ->assertSee('Hai attivato con successo il tuo account!');

        $this->assertSame('1', $cliente->fresh()->active);
    }

    public function test_activation_with_wrong_token(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency, ['active' => '0']);

        $this->get($this->host('attivacliente.php?a='.$cliente->id.'&b=sbagliato'))
            ->assertOk()
            ->assertSee('Link di Attivazione');

        $this->assertSame('0', $cliente->fresh()->active);
    }

    public function test_activation_already_active(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency, ['active' => '1']);

        $this->get($this->host('attivacliente.php?a='.$cliente->id.'&b=x'))
            ->assertSee('già attivo');
    }

    public function test_activation_unknown_user(): void
    {
        $this->get($this->host('attivacliente.php?a=999&b=x'))
            ->assertSee('Si è verificato un errore!');
    }

    // ─── cambiapassword.php + userpasswordhandler ────────────────────────

    public function test_password_page_with_valid_token_shows_form(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->get($this->host('cambiapassword.php?a='.$cliente->id.'&b='.$cliente->activation_token.'&c='.$agency->id))
            ->assertOk()
            ->assertSee('Reimpostazione Password');
    }

    public function test_password_page_with_invalid_token_shows_error(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->get($this->host('cambiapassword.php?a='.$cliente->id.'&b=falso&c='.$agency->id))
            ->assertSee('Si è verificato un errore!');
    }

    public function test_handler_changes_password_with_valid_token(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $payload = json_encode([
            'id' => (string) $cliente->id,
            'nuova_password' => 'NuovaPass!1',
            'id_agenzia' => (string) $agency->id,
            'token' => $cliente->activation_token,
        ]);

        $this->post($this->host('res/userpasswordhandler.php'), ['json' => $payload])
            ->assertOk()
            ->assertSeeText('success');

        $this->assertTrue(Hash::check('NuovaPass!1', $cliente->fresh()->password));
        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('mario@test.it')
            && str_contains($m->subjectLine, 'Password modificata'));
    }

    public function test_handler_rejects_missing_or_wrong_token(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);
        $old = $cliente->password;

        // Senza token (payload del JS legacy): rifiutato — fix di sicurezza
        $this->post($this->host('res/userpasswordhandler.php'), ['json' => json_encode([
            'id' => (string) $cliente->id, 'nuova_password' => 'Hack!1234', 'id_agenzia' => (string) $agency->id,
        ])])->assertSeeText('error');

        // Token sbagliato
        $this->post($this->host('res/userpasswordhandler.php'), ['json' => json_encode([
            'id' => (string) $cliente->id, 'nuova_password' => 'Hack!1234', 'id_agenzia' => (string) $agency->id, 'token' => 'falso',
        ])])->assertSeeText('error');

        $this->assertSame($old, $cliente->fresh()->password);
        Mail::assertNothingSent();
    }

    // ─── delete/disable/remove account ───────────────────────────────────

    public function test_delete_request_creates_token_and_sends_mail(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        $this->makeCliente($agency);

        $this->post($this->host('delete_account.php'), ['email' => 'mario@test.it'])
            ->assertRedirect();

        $req = DeleteRequest::where('email', 'mario@test.it')->first();
        $this->assertNotNull($req);
        $this->assertTrue($req->expiration->isFuture());

        Mail::assertSent(LegacyHtmlMail::class, fn (LegacyHtmlMail $m) => $m->hasTo('mario@test.it')
            && str_contains($m->htmlBody, '/remove_account.php?token='.$req->token));
    }

    public function test_delete_request_unknown_email(): void
    {
        Mail::fake();

        $response = $this->post($this->host('disable_account.php'), ['email' => 'ignoto@test.it']);
        $response->assertRedirect();
        $this->assertSame(0, DeleteRequest::count());
        Mail::assertNothingSent();
    }

    public function test_remove_with_valid_token_and_credentials_deletes_account(): void
    {
        Mail::fake();
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);
        $req = DeleteRequest::create(['email' => 'mario@test.it', 'token' => 'tok123', 'expiration' => now()->addHour()]);

        // Pagina con form
        $this->get($this->host('remove_account.php?token=tok123'))
            ->assertOk()
            ->assertSee('Eliminazione Definitiva Account');

        // Conferma
        $this->post($this->host('remove_account.php'), [
            'token' => 'tok123',
            'email' => 'mario@test.it',
            'password' => 'Password!1',
        ])->assertOk()->assertSee('Account eliminato');

        $this->assertNull(Cliente::find($cliente->id));
        $this->assertNull(DeleteRequest::find($req->id));
    }

    public function test_remove_with_expired_token(): void
    {
        DeleteRequest::create(['email' => 'x@y.it', 'token' => 'vecchio', 'expiration' => now()->subHour()]);

        $this->get($this->host('remove_account.php?token=vecchio'))
            ->assertSee('Il link è scaduto.');
    }

    public function test_remove_with_wrong_password_keeps_account(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);
        DeleteRequest::create(['email' => 'mario@test.it', 'token' => 'tok456', 'expiration' => now()->addHour()]);

        $this->from($this->host('remove_account.php?token=tok456'))
            ->post($this->host('remove_account.php'), [
                'token' => 'tok456',
                'email' => 'mario@test.it',
                'password' => 'sbagliata',
            ])->assertRedirect();

        $this->assertNotNull(Cliente::find($cliente->id));
    }
}
