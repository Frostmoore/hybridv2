<?php

namespace Tests\Feature\ApiV2;

use Illuminate\Support\Facades\Hash;

class UserTest extends V2TestCase
{
    // ─── GET user/me.php ─────────────────────────────────────────────────

    public function test_me_requires_token(): void
    {
        $this->getJson('/res/api/v2/user/me.php')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_me_returns_user_object(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $response = $this->getJson('/res/api/v2/user/me.php', $this->authHeaders($cliente))
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame((string) $cliente->id, $data['id']);
        $this->assertSame('mario.rossi', $data['username']);
        $this->assertSame('1|2026-01-01 10:00:00', $data['privacy1']);
        $this->assertArrayNotHasKey('token', $data);
        foreach ($data as $key => $value) {
            $this->assertIsString($value, "Campo $key non stringa");
        }
    }

    // ─── PATCH user/me.php ───────────────────────────────────────────────

    public function test_patch_me_empty_username_422(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->patchJson('/res/api/v2/user/me.php', ['username' => '  '], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Il campo username non può essere vuoto.');
    }

    public function test_patch_me_duplicate_username_global_409(): void
    {
        $agency = $this->makeAgency();
        $other = $this->makeAgency(['token' => 'other']);
        $cliente = $this->makeCliente($agency);
        // ⚠️ unicità GLOBALE come legacy: utente di ALTRA agenzia blocca comunque
        $this->makeCliente($other, ['username' => 'occupato', 'email' => 'x@y.it', 'cf' => 'ALTRO']);

        $this->patchJson('/res/api/v2/user/me.php', ['username' => 'occupato'], $this->authHeaders($cliente))
            ->assertStatus(409)
            ->assertJsonPath('code', 'DUPLICATE_USERNAME');
    }

    public function test_patch_me_no_fields_422(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->patchJson('/res/api/v2/user/me.php', ['altro' => 'x'], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Nessun campo modificabile fornito (username, email, playerid).');
    }

    public function test_patch_me_updates_fields(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->patchJson('/res/api/v2/user/me.php', [
            'username' => 'nuovo.nome',
            'email' => 'NUOVA@Mail.IT',
            'playerid' => 'nuovo-player',
        ], $this->authHeaders($cliente))
            ->assertOk()
            ->assertJsonPath('data.message', 'Profilo aggiornato.');

        $fresh = $cliente->fresh();
        $this->assertSame('nuovo.nome', $fresh->username);
        $this->assertSame('nuova@mail.it', $fresh->email);    // normalizzata
        $this->assertSame('nuovo-player', $fresh->playerid);
    }

    // ─── PATCH user/password.php ─────────────────────────────────────────

    public function test_password_missing_fields_422(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->patchJson('/res/api/v2/user/password.php', ['old_password' => 'x'], $this->authHeaders($cliente))
            ->assertStatus(422);
    }

    public function test_password_too_short_422(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->patchJson('/res/api/v2/user/password.php', [
            'old_password' => 'Password!1',
            'new_password' => 'corta',
        ], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'La nuova password deve essere di almeno 8 caratteri.');
    }

    public function test_password_wrong_old_401(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->patchJson('/res/api/v2/user/password.php', [
            'old_password' => 'sbagliata',
            'new_password' => 'NuovaPassword!2',
        ], $this->authHeaders($cliente))
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_password_change_success(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->patchJson('/res/api/v2/user/password.php', [
            'old_password' => 'Password!1',
            'new_password' => 'NuovaPassword!2',
        ], $this->authHeaders($cliente))
            ->assertOk()
            ->assertJsonPath('data.message', 'Password aggiornata con successo.');

        $this->assertTrue(Hash::check('NuovaPassword!2', $cliente->fresh()->password));
    }

    // ─── PATCH user/privacy.php ──────────────────────────────────────────

    public function test_privacy_invalid_id_422(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $this->patchJson('/res/api/v2/user/privacy.php', ['privacy_id' => '9', 'privacy_value' => true], $this->authHeaders($cliente))
            ->assertStatus(422)
            ->assertJsonPath('error', 'privacy_id deve essere 1, 2, 3 o 4.');
    }

    public function test_privacy_update_appends_timestamp(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $response = $this->patchJson('/res/api/v2/user/privacy.php', [
            'privacy_id' => '2',
            'privacy_value' => true,
        ], $this->authHeaders($cliente))
            ->assertOk()
            ->assertJsonPath('data.privacy_id', '2');

        $value = $response->json('data.value');
        $this->assertMatchesRegularExpression('/^1\|\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
        $this->assertSame($value, $cliente->fresh()->privacydue);

        // Revoca → '0|timestamp'
        $revoke = $this->patchJson('/res/api/v2/user/privacy.php', [
            'privacy_id' => '2',
            'privacy_value' => false,
        ], $this->authHeaders($cliente))->assertOk();
        $this->assertStringStartsWith('0|', $revoke->json('data.value'));
    }
}
