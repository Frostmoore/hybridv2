<?php

namespace Tests\Feature;

use App\Services\JwtService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Verifica che l'infrastruttura API v2 rispetti il contratto legacy:
 * wrapper {success,…}, codici errore, charset, formato 401/404/405.
 */
class ApiContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Route di prova nello stesso spazio path della v2.
        Route::get('res/api/v2/_test/echo.php', fn () => ApiResponse::ok(['ciao' => 'mondo / àèì']));
        Route::middleware('auth.jwt')->get(
            'res/api/v2/_test/protected.php',
            fn () => ApiResponse::ok(request()->attributes->get('jwt')),
        );
    }

    public function test_ok_wrapper_and_json_flags(): void
    {
        $response = $this->get('res/api/v2/_test/echo.php');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json; charset=utf-8');
        // JSON_UNESCAPED_UNICODE e JSON_UNESCAPED_SLASHES come il legacy
        $this->assertSame('{"success":true,"data":{"ciao":"mondo / àèì"}}', $response->getContent());
    }

    public function test_unknown_api_path_returns_legacy_404_format(): void
    {
        $response = $this->get('res/api/v2/inesistente.php');

        $response->assertNotFound();
        $response->assertExactJson([
            'success' => false,
            'error' => 'Risorsa non trovata.',
            'code' => 'NOT_FOUND',
        ]);
    }

    public function test_wrong_method_returns_legacy_405_format(): void
    {
        $response = $this->post('res/api/v2/_test/echo.php');

        $response->assertStatus(405);
        $response->assertExactJson([
            'success' => false,
            'error' => 'Metodo non consentito.',
            'code' => 'METHOD_NOT_ALLOWED',
        ]);
    }

    public function test_missing_token_returns_legacy_401(): void
    {
        $response = $this->get('res/api/v2/_test/protected.php');

        $response->assertUnauthorized();
        $response->assertExactJson([
            'success' => false,
            'error' => 'Token non valido o scaduto.',
            'code' => 'UNAUTHORIZED',
        ]);
    }

    public function test_invalid_token_returns_legacy_401(): void
    {
        $response = $this->get('res/api/v2/_test/protected.php', [
            'Authorization' => 'Bearer not.a.token',
        ]);

        $response->assertUnauthorized();
        $response->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_valid_token_exposes_claims_to_route(): void
    {
        $token = app(JwtService::class)->issueForUser(42, 'mario.rossi', 6);

        $response = $this->get('res/api/v2/_test/protected.php', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.sub', 42);
        $response->assertJsonPath('data.username', 'mario.rossi');
        $response->assertJsonPath('data.agency_id', 6);
    }

    public function test_stringify_matches_legacy_s_helper(): void
    {
        $this->assertSame(
            ['a' => '', 'b' => '1', 'c' => '0', 'd' => '42', 'e' => 'x'],
            ApiResponse::stringify(['a' => null, 'b' => true, 'c' => false, 'd' => 42, 'e' => 'x']),
        );
    }
}
