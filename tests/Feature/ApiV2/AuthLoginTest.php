<?php

namespace Tests\Feature\ApiV2;

use App\Services\JwtService;

class AuthLoginTest extends V2TestCase
{
    private const URL = '/res/api/v2/auth/login.php';

    public function test_missing_fields_422(): void
    {
        $this->postJson(self::URL, ['agency_id' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'I campi agency_id, username e password sono obbligatori.');
    }

    public function test_invalid_agency_id_422(): void
    {
        $this->postJson(self::URL, ['agency_id' => 'abc', 'username' => 'x', 'password' => 'y'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'agency_id non valido.');
    }

    public function test_unknown_user_401(): void
    {
        $agency = $this->makeAgency();

        $this->postJson(self::URL, ['agency_id' => (string) $agency->id, 'username' => 'ghost', 'password' => 'x'])
            ->assertStatus(401)
            ->assertExactJson([
                'success' => false,
                'error' => 'Username o password errati.',
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_wrong_password_401(): void
    {
        $agency = $this->makeAgency();
        $this->makeCliente($agency);

        $this->postJson(self::URL, ['agency_id' => (string) $agency->id, 'username' => 'mario.rossi', 'password' => 'sbagliata'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_inactive_account_403(): void
    {
        $agency = $this->makeAgency();
        $this->makeCliente($agency, ['active' => '0']);

        $this->postJson(self::URL, ['agency_id' => (string) $agency->id, 'username' => 'mario.rossi', 'password' => 'Password!1'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_INACTIVE');
    }

    public function test_successful_login_returns_token_and_user(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency, ['firstlogin' => '2025-01-01 09:00:00', 'lastlogin' => '2025-06-01 09:00:00', 'piva' => '12345678901']);

        $response = $this->postJson(self::URL, [
            'agency_id' => (string) $agency->id,
            'username' => 'mario.rossi',
            'password' => 'Password!1',
        ])->assertOk();

        $data = $response->json('data');

        // Token valido con i claims legacy
        $claims = app(JwtService::class)->decode($data['token']);
        $this->assertSame((int) $cliente->id, $claims['sub']);
        $this->assertSame('mario.rossi', $claims['username']);
        $this->assertSame((int) $agency->id, $claims['agency_id']);
        $this->assertSame((int) config('hybrid.jwt_expiry'), $data['expires_in']);

        // Oggetto user: chiavi nell'ordine legacy, valori stringa
        $this->assertSame([
            'id', 'username', 'email', 'nome', 'cognome', 'cf', 'piva', 'datadinascita',
            'agenziaid', 'playerid', 'privacy1', 'privacy2', 'privacy3', 'privacy4',
            'active', 'firstlogin', 'lastlogin', 'codiceagenzia',
        ], array_keys($data['user']));
        $this->assertSame((string) $cliente->id, $data['user']['id']);
        // piva ora esposta (allineamento API/DB/model Flutter)
        $this->assertSame('12345678901', $data['user']['piva']);
        $this->assertSame('1|2026-01-01 10:00:00', $data['user']['privacy1']);
        $this->assertSame('2025-01-01 09:00:00', $data['user']['firstlogin']);
        // lastlogin nella risposta è il NUOVO valore (adesso), non quello vecchio
        $this->assertNotSame('2025-06-01 09:00:00', $data['user']['lastlogin']);
    }

    public function test_first_login_sets_firstlogin_but_responds_with_old_empty_value(): void
    {
        $agency = $this->makeAgency();
        $cliente = $this->makeCliente($agency, ['firstlogin' => null, 'lastlogin' => null]);

        $response = $this->postJson(self::URL, [
            'agency_id' => (string) $agency->id,
            'username' => 'mario.rossi',
            'password' => 'Password!1',
        ])->assertOk();

        // Quirk legacy: la risposta mostra il firstlogin PRE-aggiornamento ('')
        $this->assertSame('', $response->json('data.user.firstlogin'));
        $this->assertNotNull($cliente->fresh()->firstlogin);
        $this->assertNotNull($cliente->fresh()->lastlogin);
    }

    public function test_login_by_email_and_cf_case_insensitive(): void
    {
        $agency = $this->makeAgency();
        $this->makeCliente($agency);

        foreach (['MARIO@TEST.IT', 'rssmra80a01h501z', 'MARIO.ROSSI'] as $identifier) {
            $this->postJson(self::URL, [
                'agency_id' => (string) $agency->id,
                'username' => $identifier,
                'password' => 'Password!1',
            ])->assertOk();
        }
    }

    public function test_user_of_other_agency_cannot_login(): void
    {
        $agency = $this->makeAgency();
        $other = $this->makeAgency(['token' => 'other']);
        $this->makeCliente($other);

        $this->postJson(self::URL, [
            'agency_id' => (string) $agency->id,
            'username' => 'mario.rossi',
            'password' => 'Password!1',
        ])->assertStatus(401);
    }
}
