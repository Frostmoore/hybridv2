<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RefreshToken;

/**
 * Emissione, validazione e rotazione dei refresh token.
 *
 * - Il token in chiaro (64 hex) è restituito UNA sola volta al client; nel DB
 *   se ne salva solo l'hash SHA-256 (un leak del DB non espone token usabili).
 * - Rotazione one-time: `rotate()` valida il token presentato, lo revoca e ne
 *   emette uno nuovo, così un refresh token vale per un solo /refresh.
 */
final class RefreshTokenService
{
    public function __construct(private readonly int $expiry) {}

    public function expiry(): int
    {
        return $this->expiry;
    }

    /** Emette un nuovo refresh token e ne ritorna il valore in chiaro. */
    public function issue(int $clienteId, int $agencyId): string
    {
        $plain = bin2hex(random_bytes(32));

        RefreshToken::create([
            'token_hash' => hash('sha256', $plain),
            'cliente_id' => $clienteId,
            'agency_id' => $agencyId,
            'expires_at' => now()->addSeconds($this->expiry),
        ]);

        return $plain;
    }

    /** Riga valida (non revocata, non scaduta) per il token in chiaro, o null. */
    public function findValid(string $plain): ?RefreshToken
    {
        if ($plain === '') {
            return null;
        }

        $row = RefreshToken::where('token_hash', hash('sha256', $plain))->first();
        if ($row === null || $row->revoked_at !== null || $row->expires_at->isPast()) {
            return null;
        }

        return $row;
    }

    public function revoke(RefreshToken $row): void
    {
        $row->revoked_at = now();
        $row->save();
    }
}
