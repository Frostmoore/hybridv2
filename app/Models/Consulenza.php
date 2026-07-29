<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Richiesta di consulenza con appuntamento (tabella `consulenze`).
 * Feature speciale per-agenzia: vedi AgenziaSpeciale::consulenza_attiva.
 */
class Consulenza extends Model
{
    protected $table = 'consulenze';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'appuntamento_il' => 'datetime',
            'privacy' => 'boolean',
        ];
    }
}
