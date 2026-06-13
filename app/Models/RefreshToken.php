<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Refresh token per l'auto-login dell'app (tabella `refresh_tokens`).
 * Si conserva solo l'hash; il valore in chiaro vive solo lato client.
 */
class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
