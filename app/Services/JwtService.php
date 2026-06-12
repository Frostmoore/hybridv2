<?php

declare(strict_types=1);

namespace App\Services;

/**
 * JWT HS256 compatibile byte-per-byte con l'implementazione legacy
 * (PH/res/api/v2/_bootstrap.php: jwt_encode/jwt_decode).
 *
 * Claims emessi dal login: sub (int), username, agency_id (int), iat, exp.
 */
final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $expiry,
    ) {
        if ($this->secret === '') {
            throw new \RuntimeException('JWT_SECRET non configurato.');
        }
    }

    public function expiry(): int
    {
        return $this->expiry;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(array $payload): string
    {
        $header = $this->b64urlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->b64urlEncode((string) json_encode($payload));
        $signature = $this->b64urlEncode(hash_hmac('sha256', "$header.$body", $this->secret, true));

        return "$header.$body.$signature";
    }

    /**
     * Token per un utente dell'app (stessi claims del login legacy).
     */
    public function issueForUser(int $userId, string $username, int $agencyId): string
    {
        return $this->encode([
            'sub' => $userId,
            'username' => $username,
            'agency_id' => $agencyId,
            'iat' => time(),
            'exp' => time() + $this->expiry,
        ]);
    }

    /**
     * @return array<string, mixed>|null null se firma non valida, malformato o scaduto
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $signature] = $parts;

        $expected = $this->b64urlEncode(hash_hmac('sha256', "$header.$body", $this->secret, true));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $claims = json_decode($this->b64urlDecode($body), true);
        if (! is_array($claims)) {
            return null;
        }
        if (isset($claims['exp']) && $claims['exp'] < time()) {
            return null;
        }

        return $claims;
    }

    private function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
