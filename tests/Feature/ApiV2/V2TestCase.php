<?php

namespace Tests\Feature\ApiV2;

use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

abstract class V2TestCase extends TestCase
{
    use RefreshDatabase;

    protected function makeAgency(array $overrides = []): AgenziaNew
    {
        return AgenziaNew::create(array_merge([
            'nome_app' => 'Test App',
            'nome_agenzia' => 'Agenzia Test',
            'token' => 'agency-token-123',
            'token_interno' => 'internal-token-123',
            'attiva' => '1',
            'denuncia_mail' => 'sinistri@test.it',
            'quick_email' => 'quick@test.it',
            'info_email_sedi' => 'info@test.it|info2@test.it',
            'versione_app' => 'v2',
        ], $overrides));
    }

    protected function makeCliente(AgenziaNew $agency, array $overrides = []): Cliente
    {
        return Cliente::create(array_merge([
            'username' => 'mario.rossi',
            'password' => Hash::make('Password!1'),
            'email' => 'mario@test.it',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'cf' => 'RSSMRA80A01H501Z',
            'datadinascita' => '1980-01-01',
            'agenziaid' => $agency->id,
            'playerid' => 'player-1',
            'privacyuno' => '1|2026-01-01 10:00:00',
            'privacydue' => '1|2026-01-01 10:00:00',
            'privacytre' => '0|2026-01-01 10:00:00',
            'privacyquattro' => '0|2026-01-01 10:00:00',
            'active' => '1',
            'activation_token' => str_repeat('ab', 16),
        ], $overrides));
    }

    protected function tokenFor(Cliente $cliente): string
    {
        return app(JwtService::class)->issueForUser(
            (int) $cliente->id,
            (string) $cliente->username,
            (int) $cliente->agenziaid,
        );
    }

    /** @return array<string, string> */
    protected function authHeaders(Cliente $cliente): array
    {
        return ['Authorization' => 'Bearer '.$this->tokenFor($cliente)];
    }
}
