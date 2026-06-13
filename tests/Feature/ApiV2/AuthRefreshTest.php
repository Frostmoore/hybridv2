<?php

namespace Tests\Feature\ApiV2;

use App\Models\RefreshToken;
use App\Services\RefreshTokenService;

class AuthRefreshTest extends V2TestCase
{
    private const LOGIN = '/res/api/v2/auth/login.php';
    private const REFRESH = '/res/api/v2/auth/refresh.php';
    private const LOGOUT = '/res/api/v2/auth/logout.php';

    /** Esegue il login e ritorna il refresh_token emesso. */
    private function loginRefreshToken(int $agencyId): string
    {
        return $this->postJson(self::LOGIN, [
            'agency_id' => (string) $agencyId,
            'username' => 'mario.rossi',
            'password' => 'Password!1',
        ])->assertOk()->json('data.refresh_token');
    }

    public function test_login_returns_refresh_token(): void
    {
        $agency = $this->makeAgency();
        $this->makeCliente($agency);

        $token = $this->loginRefreshToken((int) $agency->id);
        $this->assertNotEmpty($token);
        // Nel DB si salva solo l'hash, mai il valore in chiaro
        $this->assertDatabaseHas('refresh_tokens', ['token_hash' => hash('sha256', $token)]);
        $this->assertDatabaseMissing('refresh_tokens', ['token_hash' => $token]);
    }

    public function test_refresh_rotates_and_returns_new_jwt(): void
    {
        $agency = $this->makeAgency();
        $this->makeCliente($agency);
        $token = $this->loginRefreshToken((int) $agency->id);

        $res = $this->postJson(self::REFRESH, ['refresh_token' => $token])->assertOk();
        $new = $res->json('data.refresh_token');

        $this->assertNotEmpty($res->json('data.token'));
        $this->assertNotSame($token, $new);                 // ruotato
        $this->assertSame((int) config('hybrid.jwt_expiry'), $res->json('data.expires_in'));

        // Il token vecchio non è più valido (one-time)
        $this->postJson(self::REFRESH, ['refresh_token' => $token])->assertStatus(401);
        // Il nuovo invece funziona
        $this->postJson(self::REFRESH, ['refresh_token' => $new])->assertOk();
    }

    public function test_refresh_invalid_token_401(): void
    {
        $this->postJson(self::REFRESH, ['refresh_token' => 'inesistente'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_refresh_expired_token_401(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency);

        $plain = app(RefreshTokenService::class)->issue((int) $cliente->id, (int) $agency->id);
        // Forza la scadenza nel passato
        RefreshToken::where('token_hash', hash('sha256', $plain))->update(['expires_at' => now()->subDay()]);

        $this->postJson(self::REFRESH, ['refresh_token' => $plain])->assertStatus(401);
    }

    public function test_refresh_inactive_account_403_and_revokes(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency, ['active' => '0']);

        $plain = app(RefreshTokenService::class)->issue((int) $cliente->id, (int) $agency->id);

        $this->postJson(self::REFRESH, ['refresh_token' => $plain])
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_INACTIVE');

        // Token revocato → secondo tentativo 401
        $this->postJson(self::REFRESH, ['refresh_token' => $plain])->assertStatus(401);
    }

    public function test_logout_revokes_refresh_token(): void
    {
        $agency = $this->makeAgency();
        $this->makeCliente($agency);
        $token = $this->loginRefreshToken((int) $agency->id);

        $this->postJson(self::LOGOUT, ['refresh_token' => $token])
            ->assertOk()
            ->assertJsonPath('data.message', 'Logout effettuato.');

        $this->postJson(self::REFRESH, ['refresh_token' => $token])->assertStatus(401);
    }

    public function test_logout_unknown_token_is_ok(): void
    {
        $this->postJson(self::LOGOUT, ['refresh_token' => 'qualsiasi'])->assertOk();
    }
}
