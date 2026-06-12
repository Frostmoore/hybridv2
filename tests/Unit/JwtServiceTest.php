<?php

namespace Tests\Unit;

use App\Services\JwtService;
use PHPUnit\Framework\TestCase;

class JwtServiceTest extends TestCase
{
    private JwtService $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JwtService(secret: str_repeat('s3cret-', 10), expiry: 3600);
    }

    public function test_roundtrip_encode_decode(): void
    {
        $claims = ['sub' => 42, 'username' => 'mario.rossi', 'agency_id' => 6, 'iat' => time(), 'exp' => time() + 100];

        $decoded = $this->jwt->decode($this->jwt->encode($claims));

        $this->assertSame($claims, $decoded);
    }

    public function test_issue_for_user_contains_legacy_claims(): void
    {
        $claims = $this->jwt->decode($this->jwt->issueForUser(7, 'pippo', 6));

        $this->assertNotNull($claims);
        $this->assertSame(7, $claims['sub']);
        $this->assertSame('pippo', $claims['username']);
        $this->assertSame(6, $claims['agency_id']);
        $this->assertSame($claims['iat'] + 3600, $claims['exp']);
    }

    public function test_compatible_with_legacy_php_implementation(): void
    {
        // Token generato con il codice legacy (jwt_encode di _bootstrap.php)
        // usando lo stesso secret di questo test: l'output deve combaciare.
        $payload = ['sub' => 1, 'username' => 'x', 'agency_id' => 2, 'iat' => 1700000000, 'exp' => 9999999999];

        $h = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $p = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');
        $s = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", str_repeat('s3cret-', 10), true)), '+/', '-_'), '=');

        $this->assertSame("$h.$p.$s", $this->jwt->encode($payload));
        $this->assertSame($payload, $this->jwt->decode("$h.$p.$s"));
    }

    public function test_rejects_tampered_signature(): void
    {
        $token = $this->jwt->issueForUser(1, 'a', 1);
        $tampered = substr($token, 0, -2).'xx';

        $this->assertNull($this->jwt->decode($tampered));
    }

    public function test_rejects_token_signed_with_other_secret(): void
    {
        $other = new JwtService(secret: 'another-secret-another-secret', expiry: 3600);

        $this->assertNull($this->jwt->decode($other->issueForUser(1, 'a', 1)));
    }

    public function test_rejects_expired_token(): void
    {
        $token = $this->jwt->encode(['sub' => 1, 'exp' => time() - 10]);

        $this->assertNull($this->jwt->decode($token));
    }

    public function test_rejects_malformed_tokens(): void
    {
        $this->assertNull($this->jwt->decode(''));
        $this->assertNull($this->jwt->decode('abc'));
        $this->assertNull($this->jwt->decode('a.b'));
        $this->assertNull($this->jwt->decode('a.b.c.d'));
        $this->assertNull($this->jwt->decode('!!!.???.***'));
    }
}
