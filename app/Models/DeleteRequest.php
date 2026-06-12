<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Richiesta di cancellazione account (tabella `delete_requests`).
 */
class DeleteRequest extends Model
{
    protected $table = 'delete_requests';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expiration' => 'datetime'];
    }
}
